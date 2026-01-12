<?php

namespace App\Http\Controllers\SuperStar;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use App\Models\SuperstarPost;

class SuperstarPostController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/superstar/posts",
     *     summary="Get superstar posts",
     *     description="Get authenticated superstar's posts with pagination and filtering options",
     *     tags={"Superstar Posts"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number",
     *         required=false,
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of items per page",
     *         required=false,
     *         @OA\Schema(type="integer", default=15)
     *     ),
     *     @OA\Parameter(
     *         name="media_type",
     *         in="query",
     *         description="Filter by media type",
     *         required=false,
     *         @OA\Schema(type="string", enum={"image", "video"})
     *     ),
     *     @OA\Parameter(
     *         name="is_pg",
     *         in="query",
     *         description="Filter by PG rating",
     *         required=false,
     *         @OA\Schema(type="boolean")
     *     ),
     *     @OA\Parameter(
     *         name="is_disturbing",
     *         in="query",
     *         description="Filter by disturbing content",
     *         required=false,
     *         @OA\Schema(type="boolean")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Posts retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="posts", type="array", @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="user_id", type="integer", example=5),
     *                 @OA\Property(property="media_type", type="string", example="image"),
     *                 @OA\Property(property="resource_type", type="string", example="upload"),
     *                 @OA\Property(property="resource_url_path", type="string", example="posts/image123.jpg"),
     *                 @OA\Property(property="description", type="string", nullable=true, example="Check out my new post!"),
     *                 @OA\Property(property="is_pg", type="boolean", example=true),
     *                 @OA\Property(property="is_disturbing", type="boolean", example=false),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2024-01-15T10:30:00Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2024-01-15T10:35:00Z"),
     *                 @OA\Property(property="superstar", type="object",
     *                     @OA\Property(property="id", type="integer", example=5),
     *                     @OA\Property(property="display_name", type="string", example="John Doe"),
     *                     @OA\Property(property="username", type="string", example="johndoe"),
     *                     @OA\Property(property="email", type="string", format="email", example="superstar@example.com")
     *                 )
     *             )),
     *             @OA\Property(property="pagination", type="object",
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(property="last_page", type="integer", example=5),
     *                 @OA\Property(property="per_page", type="integer", example=15),
     *                 @OA\Property(property="total", type="integer", example=75),
     *                 @OA\Property(property="from", type="integer", example=1),
     *                 @OA\Property(property="to", type="integer", example=15),
     *                 @OA\Property(property="has_more_pages", type="boolean", example=true)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthorized")
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        $superstar = $request->user();
        
        // Pagination parameters
        $page = $request->get('page', 1);
        $perPage = $request->get('per_page', 15);
        
        // Filter parameters
        $mediaType = $request->get('media_type');
        $isPg = $request->get('is_pg');
        $isDisturbing = $request->get('is_disturbing');
        
        $query = SuperstarPost::where('user_id', $superstar->id);
        
        // Apply filters
        if ($mediaType) {
            $query->where('media_type', $mediaType);
        }
        if ($isPg !== null) {
            $query->where('is_pg', filter_var($isPg, FILTER_VALIDATE_BOOLEAN));
        }
        if ($isDisturbing !== null) {
            $query->where('is_disturbing', filter_var($isDisturbing, FILTER_VALIDATE_BOOLEAN));
        }
        
        $posts = $query->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);
            
        return response()->json([
            'posts' => $posts->items(),
            'pagination' => [
                'current_page' => $posts->currentPage(),
                'last_page' => $posts->lastPage(),
                'per_page' => $posts->perPage(),
                'total' => $posts->total(),
                'from' => $posts->firstItem(),
                'to' => $posts->lastItem(),
                'has_more_pages' => $posts->hasMorePages()
            ]
        ]);
    }
    
    /**
     * @OA\Post(
     *     path="/api/superstar/posts",
     *     summary="Create superstar post",
     *     description="Create a new post for the authenticated superstar",
     *     tags={"Superstar Posts"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"media_type","resource_type","resource_url_path"},
     *             @OA\Property(property="media_type", type="string", enum={"image", "video"}, example="image"),
     *             @OA\Property(property="resource_type", type="string", enum={"upload", "url"}, example="upload"),
     *             @OA\Property(property="resource_url_path", type="string", maxLength=500, example="posts/image123.jpg"),
     *             @OA\Property(property="description", type="string", nullable=true, example="Check out my new post!"),
     *             @OA\Property(property="is_pg", type="boolean", example=true),
     *             @OA\Property(property="is_disturbing", type="boolean", example=false)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Post created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Post created successfully"),
     *             @OA\Property(property="post", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="user_id", type="integer", example=5),
     *                 @OA\Property(property="media_type", type="string", example="image"),
     *                 @OA\Property(property="resource_type", type="string", example="upload"),
     *                 @OA\Property(property="resource_url_path", type="string", example="posts/image123.jpg"),
     *                 @OA\Property(property="description", type="string", nullable=true, example="Check out my new post!"),
     *                 @OA\Property(property="is_pg", type="boolean", example=true),
     *                 @OA\Property(property="is_disturbing", type="boolean", example=false),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2024-01-15T10:30:00Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2024-01-15T10:35:00Z"),
     *                 @OA\Property(property="superstar", type="object",
     *                     @OA\Property(property="id", type="integer", example=5),
     *                     @OA\Property(property="display_name", type="string", example="John Doe"),
     *                     @OA\Property(property="username", type="string", example="johndoe"),
     *                     @OA\Property(property="email", type="string", format="email", example="superstar@example.com")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthorized")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Validation failed"),
     *             @OA\Property(property="errors", type="object", example={"media_type": {"The media type field is required."}})
     *         )
     *     )
     * )
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'media_type' => 'required|in:image,video',
            'resource_type' => 'required|in:upload,url',
            'resource_url_path' => 'required|string|max:500',
            'description' => 'nullable|string',
            'is_pg' => 'boolean',
            'is_disturbing' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $superstar = $request->user();
        
        $post = SuperstarPost::create([
            'user_id' => $superstar->id,
            'media_type' => $request->media_type,
            'resource_type' => $request->resource_type,
            'resource_url_path' => $request->resource_url_path,
            'description' => $request->description,
            'is_pg' => $request->boolean('is_pg', false),
            'is_disturbing' => $request->boolean('is_disturbing', false)
        ]);
        
        return response()->json([
            'message' => 'Post created successfully',
            'post' => $post->load('superstar')
        ], 201);
    }
    
    /**
     * @OA\Get(
     *     path="/api/superstar/posts/{id}",
     *     summary="Get specific superstar post",
     *     description="Get a specific post by ID for the authenticated superstar",
     *     tags={"Superstar Posts"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Post ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Post retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="post", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="user_id", type="integer", example=5),
     *                 @OA\Property(property="media_type", type="string", example="image"),
     *                 @OA\Property(property="resource_type", type="string", example="upload"),
     *                 @OA\Property(property="resource_url_path", type="string", example="posts/image123.jpg"),
     *                 @OA\Property(property="description", type="string", nullable=true, example="Check out my new post!"),
     *                 @OA\Property(property="is_pg", type="boolean", example=true),
     *                 @OA\Property(property="is_disturbing", type="boolean", example=false),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2024-01-15T10:30:00Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2024-01-15T10:35:00Z"),
     *                 @OA\Property(property="superstar", type="object",
     *                     @OA\Property(property="id", type="integer", example=5),
     *                     @OA\Property(property="display_name", type="string", example="John Doe"),
     *                     @OA\Property(property="username", type="string", example="johndoe"),
     *                     @OA\Property(property="email", type="string", format="email", example="superstar@example.com")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthorized")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Post not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Post not found")
     *         )
     *     )
     * )
     */
    public function show(Request $request, $id)
    {
        $superstar = $request->user();
        
        $post = SuperstarPost::where('user_id', $superstar->id)
            ->where('id', $id)
            ->with('superstar')
            ->first();
            
        if (!$post) {
            return response()->json([
                'message' => 'Post not found'
            ], 404);
        }
        
        return response()->json(['post' => $post]);
    }
    
    /**
     * @OA\Put(
     *     path="/api/superstar/posts/{id}",
     *     summary="Update superstar post",
     *     description="Update an existing post for the authenticated superstar",
     *     tags={"Superstar Posts"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Post ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="media_type", type="string", enum={"image", "video"}, example="video"),
     *             @OA\Property(property="resource_type", type="string", enum={"upload", "url"}, example="url"),
     *             @OA\Property(property="resource_url_path", type="string", maxLength=500, example="https://example.com/video.mp4"),
     *             @OA\Property(property="description", type="string", nullable=true, example="Updated post description!"),
     *             @OA\Property(property="is_pg", type="boolean", example=false),
     *             @OA\Property(property="is_disturbing", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Post updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Post updated successfully"),
     *             @OA\Property(property="post", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="user_id", type="integer", example=5),
     *                 @OA\Property(property="media_type", type="string", example="video"),
     *                 @OA\Property(property="resource_type", type="string", example="url"),
     *                 @OA\Property(property="resource_url_path", type="string", example="https://example.com/video.mp4"),
     *                 @OA\Property(property="description", type="string", nullable=true, example="Updated post description!"),
     *                 @OA\Property(property="is_pg", type="boolean", example=false),
     *                 @OA\Property(property="is_disturbing", type="boolean", example=true),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2024-01-15T10:30:00Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2024-01-15T11:00:00Z"),
     *                 @OA\Property(property="superstar", type="object",
     *                     @OA\Property(property="id", type="integer", example=5),
     *                     @OA\Property(property="display_name", type="string", example="John Doe"),
     *                     @OA\Property(property="username", type="string", example="johndoe"),
     *                     @OA\Property(property="email", type="string", format="email", example="superstar@example.com")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthorized")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Post not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Post not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Validation failed"),
     *             @OA\Property(property="errors", type="object", example={"media_type": {"The media type must be image or video."}})
     *         )
     *     )
     * )
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'media_type' => 'in:image,video',
            'resource_type' => 'in:upload,url',
            'resource_url_path' => 'string|max:500',
            'description' => 'nullable|string',
            'is_pg' => 'boolean',
            'is_disturbing' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $superstar = $request->user();
        
        $post = SuperstarPost::where('user_id', $superstar->id)
            ->where('id', $id)
            ->first();
            
        if (!$post) {
            return response()->json([
                'message' => 'Post not found'
            ], 404);
        }
        
        $updateData = [];
        
        if ($request->has('media_type')) {
            $updateData['media_type'] = $request->media_type;
        }
        if ($request->has('resource_type')) {
            $updateData['resource_type'] = $request->resource_type;
        }
        if ($request->has('resource_url_path')) {
            $updateData['resource_url_path'] = $request->resource_url_path;
        }
        if ($request->has('description')) {
            $updateData['description'] = $request->description;
        }
        if ($request->has('is_pg')) {
            $updateData['is_pg'] = $request->boolean('is_pg');
        }
        if ($request->has('is_disturbing')) {
            $updateData['is_disturbing'] = $request->boolean('is_disturbing');
        }
        
        $post->update($updateData);
        
        return response()->json([
            'message' => 'Post updated successfully',
            'post' => $post->load('superstar')
        ]);
    }
    
    /**
     * @OA\Delete(
     *     path="/api/superstar/posts/{id}",
     *     summary="Delete superstar post",
     *     description="Delete a specific post for the authenticated superstar",
     *     tags={"Superstar Posts"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Post ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Post deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Post deleted successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthorized")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Post not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Post not found")
     *         )
     *     )
     * )
     */
    public function destroy(Request $request, $id)
    {
        $superstar = $request->user();
        
        $post = SuperstarPost::where('user_id', $superstar->id)
            ->where('id', $id)
            ->first();
            
        if (!$post) {
            return response()->json([
                'message' => 'Post not found'
            ], 404);
        }
        
        // Delete file if it's an upload
        if ($post->resource_type === 'upload' && Storage::disk('public')->exists($post->resource_url_path)) {
            Storage::disk('public')->delete($post->resource_url_path);
        }
        
        $post->delete();
        
        return response()->json([
            'message' => 'Post deleted successfully'
        ]);
    }
}
