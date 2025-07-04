<?php
namespace App\Jobs;

use App\Contracts\VideoManagerServiceInterface;
use Log;

class UpdateFavVideosJob extends BaseScheduledRateLimitedJob
{

    public $queue = 'default';

    /**
     * Create a new job instance.
     */
    public function __construct(public array $fav, public ?int $page = null)
    {
        //
    }

    /**
     * 获取限流键名
     */
    protected function getRateLimitKey(): string
    {
        return 'update_fav_videos_job';
    }

    /**
     * 获取最大处理数量 - 每分钟最多5个收藏夹更新
     */
    protected function getMaxProcessCount(): int
    {
        return 5;
    }

    /**
     * 获取时间窗口 - 2分钟
     */
    protected function getTimeWindow(): int
    {
        return 120;
    }

    /**
     * 具体的处理逻辑
     */
    protected function process(): void
    {
        Log::info('Update fav videos job start');
        $videoManagerService = app(VideoManagerServiceInterface::class);

        $videoManagerService->updateFavVideos($this->fav, $this->page);
        Log::info('Update fav videos job end', ['fav_title' => $this->fav['title'], 'page' => $this->page]);
    }

    public function displayName(): string
    {
        return __CLASS__ . ' ' . $this->fav['title'] . ' page: ' . $this->page;
    }
}
