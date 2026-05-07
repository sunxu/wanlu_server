<?php
// 新建应用
namespace App\Console\Commands\Tools;

use Cache;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PlacementEcpm extends Command
{
    /**
     * Command Name
     * @var string
     */
    protected $signature = 'placement-ecpm';

    /**
     * Command description
     * @var string
     */
    protected $description = '更新广告位价格';

    protected $day = 2;

    /**
     * Command Handle
     */
    public function handle()
    {
        $mediaAd = DB::connection('uc')
            ->table('app_media_ad')
            ->select(['ad_id as placement_id', 'price as ecpm'])
            ->where('price', '>', 0)
            ->where('ad_id', '!=', '')
            ->get()
            ->toArray();
        $media = $this->getMedia();
        $placement = [];
        foreach ($mediaAd as $value) {
            $placement[data_get($value, 'placement_id')] = data_get($value, 'ecpm');
        }
        foreach ($media as $value) {
            $placement[data_get($value, 'placement_id')] = data_get($value, 'ecpm');
        }
        foreach ($placement as $key => $value) {
            Cache::put('ecpm:' . $key, $value);
            DB::table('com_placement_ecpm')->updateOrInsert([
                'placement_id' => $key
            ], [
                'placement_id' => $key,
                'ecpm' => $value,
                'updated_time' => time()
            ]);
        }
    }

    public function getMedia()
    {
        $media = [];
        $mediaModel = DB::connection('uc')->table('media_income')
            ->where('date', '>=', date("Y-m-d", time() - 24 * 60 * 60 * $this->day));
        $count = $mediaModel->count();
        $limit = 5000;
        for ($offset = 0; $offset < ceil($count / $limit); $offset++) {
            $data = $mediaModel
                ->select(['placement_id', 'ecpm', 'date'])
                ->limit($limit)
                ->offset($offset * $limit)
                ->get()
                ->toArray();
            foreach ($data as $value) {
                $placementId = data_get($value, 'placement_id');
                if (isset($media[$placementId]) && strtotime($media[$placementId]['date']) > strtotime(data_get($value, 'date'))) {
                    continue;
                }
                $media[$placementId] = [
                    'placement_id' => data_get($value, 'placement_id'),
                    'ecpm' => data_get($value, 'ecpm'),
                    'date' => data_get($value, 'date'),
                ];
            }
        }

        return collect($media)->values()->toArray();
    }
}
