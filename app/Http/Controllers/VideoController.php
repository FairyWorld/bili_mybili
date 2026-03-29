<?php

namespace App\Http\Controllers;

use App\Jobs\DownloadDanmakuJob;
use App\Models\Video;
use App\Services\DanmakuConverterService;
use App\Services\VideoManager\Contracts\DanmakuServiceInterface;
use App\Services\VideoManager\Contracts\FavoriteServiceInterface;
use App\Services\VideoManager\Contracts\VideoServiceInterface;
use Illuminate\Http\Request;

class VideoController extends Controller
{
    public function __construct(
        public VideoServiceInterface $videoService,
        public FavoriteServiceInterface $favoriteService,
        public DanmakuServiceInterface $danmakuService,
        public DanmakuConverterService $danmakuConverterService
    ) {}

    public function index(Request $request)
    {
        $data = $request->validate([
            'query' => 'nullable|string',
            'page' => 'nullable|integer|min:1',
            'status' => 'nullable|string',
            'downloaded' => 'nullable|string',
            'multi_part' => 'nullable|string',
            'fav_id' => 'nullable|integer',
            'page_size' => 'nullable|integer|min:1',
        ]);
        $page = $data['page'] ?? 1;
        $perPage = 30;
        $result = $this->videoService->getVideosByPage([
            'query' => $data['query'] ?? '',
            'status' => $data['status'] ?? '',
            'downloaded' => $data['downloaded'] ?? '',
            'multi_part' => $data['multi_part'] ?? '',
            'fav_id' => $data['fav_id'] ?? '',
        ], $page, intval($data['page_size'] ?? $perPage));

        return response()->json([
            'stat' => $result['stat'],
            'list' => $result['list'],
        ]);
    }

    public function destroy(Request $request, string $id)
    {
        if (config('services.bilibili.setting_read_only')) {
            abort(403);
        }
        $validated = $request->validate([
            'extend_ids' => 'nullable|array',
            'extend_ids.*' => 'integer',
            'permanent' => 'nullable|boolean',
            'requeue' => 'nullable|boolean',
        ]);
        // 补充其他ID
        $extend_ids = $validated['extend_ids'] ?? null;
        if ($extend_ids && is_array($extend_ids)) {
            $ids = array_merge([$id], $extend_ids);
        } else {
            $ids = [$id];
        }
        $ids = array_map('intval', $ids);
        $deletedIds = $this->videoService->deleteVideos($ids, [
            'permanent' => (bool) ($validated['permanent'] ?? true),
            'requeue' => (bool) ($validated['requeue'] ?? false),
        ]);
        if ($deletedIds) {
            return response()->json([
                'code' => 0,
                'message' => 'Video deleted successfully',
                'deleted_ids' => $deletedIds,
            ]);
        } else {
            return response()->json([
                'code' => 1,
                'message' => 'Video deletion failed',
            ]);
        }
    }

    public function show(Request $request, int $id)
    {
        $video = $this->videoService->getVideoInfo($id, true);
        if ($video) {
            $video->load(['favorite', 'subscriptions', 'upper', 'audioPart']);
            $video->video_parts = $this->videoService->getAllPartsVideoForUser($video);
            $video->danmaku_count = $this->danmakuService->getVideoDanmakuCount($video);

            return response()->json($video);
        }
        abort(404);
    }

    public function progress()
    {
        $list = $this->videoService->getVideosCache();
        $data = [
            'data' => $list,
            'stat' => $this->videoService->getVideosStat([]),
        ];

        return response()->json($data, 200, []);
    }


    /**
     * 按视频 ID 排队拉取最新弹幕：单 P 立即执行；多 P 每个分 P 递增延迟 1 分钟。
     */
    public function refreshDanmaku(Request $request, int $id)
    {
        if (config('services.bilibili.setting_read_only')) {
            abort(403);
        }

        $video = Video::query()->with(['parts' => fn ($q) => $q->orderBy('page')])->find($id);
        if (! $video) {
            abort(404);
        }

        if ($video->isAudio()) {
            return response()->json([
                'code' => 1,
                'message' => '音频稿件不支持分 P 弹幕同步',
                'parts_queued' => 0,
            ]);
        }

        $parts = $video->parts;
        if ($parts->isEmpty()) {
            return response()->json([
                'code' => 1,
                'message' => '暂无可更新的分 P',
                'parts_queued' => 0,
            ]);
        }

        foreach ($parts->values() as $index => $part) {
            DownloadDanmakuJob::dispatch($part)->delay(now()->addMinutes($index));
        }

        return response()->json([
            'code' => 0,
            'message' => '弹幕更新任务已加入队列',
            'parts_queued' => $parts->count(),
        ]);
    }


    /**
     * 获取指定 CID 的弹幕数据（新格式）
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function danmaku(Request $request)
    {
        $cid = $request->input('id');

        if (! $cid) {
            return response()->json([
                'code' => 1,
                'message' => 'CID 参数不能为空',
                'data' => [],
            ]);
        }

        // 获取原始弹幕数据
        $danmakuList = $this->danmakuService->getDanmaku($cid);

        // 转换为新格式
        $convertedData = $this->danmakuConverterService->convert($danmakuList);

        return response()->json([
            'code' => 0,
            'data' => $convertedData,
        ]);
    }
}
