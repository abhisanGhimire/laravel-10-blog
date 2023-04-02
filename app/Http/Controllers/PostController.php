<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Post;
use App\Models\PostView;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PostController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        // Latest post
//        $latestPost = Post::query()
//            ->where('active', '=', 1)
//            ->whereDate('published_at', '<', Carbon::now())
//            ->orderBy('published_at', 'desc')
//            ->limit(1)
//            ->first();

        // Show the most popular 3 posts
//        $posts = DB::table('posts')
//            ->leftJoin('upvote_downvotes', 'posts.id', '=', 'upvote_downvotes.post_id')
//            ->select('posts.*', DB::raw('COUNT(upvote_downvotes.id) as upvote_count'))
//            ->where(function ($query) {
//                $query->whereNull('upvote_downvotes.is_upvote')
//                    ->orWhere('upvote_downvotes.is_upvote', '=', 1);
//            })
//            ->where('active', '=', 1)
//            ->whereDate('published_at', '<', Carbon::now())
//            ->groupBy('posts.id')
//            ->orderByDesc('upvote_count')
//            ->limit(3)
//            ->get();

        // Show recommended articles based on user likes and views
        $userId = auth()->user()->id;

        $userId = 1;

        $posts = DB::table('posts')
            ->leftJoin('category_post as cp', 'posts.id', '=', 'cp.post_id')
            ->leftJoin(DB::raw('(SELECT cp.category_id, cp.post_id FROM upvote_downvotes
                        JOIN category_post cp ON upvote_downvotes.post_id = cp.post_id
                        WHERE upvote_downvotes.is_upvote = 1 AND upvote_downvotes.user_id = ?) AS t'), function ($join) {
                $join->on('t.category_id', '=', 'cp.category_id')
                    ->on('t.post_id', '<>', 'cp.post_id');
            })
            ->select('posts.*')
            ->where('posts.id', '<>', DB::raw('t.post_id'))
            ->setBindings([$userId])
            ->get();


        // Show top 5 categories with recent 3 articles
        $posts = DB::table('posts')
            ->leftJoin('upvote_downvotes', 'posts.id', '=', 'upvote_downvotes.post_id')
            ->select('posts.*', DB::raw('COUNT(upvote_downvotes.id) as upvote_count'))
            ->where(function ($query) {
                $query->whereNull('upvote_downvotes.is_upvote')
                    ->orWhere('upvote_downvotes.is_upvote', '=', 1);
            })
            ->groupBy('posts.id')
            ->orderByDesc('upvote_count')
            ->limit(5)
            ->get();

        dd($posts);

        return view('home', compact('posts'));
    }


    /**
     * Display the specified resource.
     */
    public function show(Post $post, Request $request)
    {
        if (!$post->active || $post->published_at > Carbon::now()) {
            throw new NotFoundHttpException();
        }

        $next = Post::query()
            ->where('active', true)
            ->whereDate('published_at', '<=', Carbon::now())
            ->whereDate('published_at', '<', $post->published_at)
            ->orderBy('published_at', 'desc')
            ->limit(1)
            ->first();

        $prev = Post::query()
            ->where('active', true)
            ->whereDate('published_at', '<=', Carbon::now())
            ->whereDate('published_at', '>', $post->published_at)
            ->orderBy('published_at', 'asc')
            ->limit(1)
            ->first();

        $user = $request->user();
        PostView::create([
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'post_id' => $post->id,
            'user_id' => $user?->id
        ]);

        return view('post.view', compact('post', 'prev', 'next'));
    }

    public function byCategory(Category $category)
    {
        $posts = Post::query()
            ->join('category_post', 'posts.id', '=', 'category_post.post_id')
            ->where('category_post.category_id', '=', $category->id)
            ->where('active', '=', true)
            ->whereDate('published_at', '<=', Carbon::now())
            ->orderBy('published_at', 'desc')
            ->paginate(10);

        return view('post.index', compact('posts', 'category'));
    }
}
