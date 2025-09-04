<?php
namespace app\controller\movie;

use app\BaseController;
use think\facade\Db;
use think\facade\Log;
use think\facade\Request;

class Movie extends BaseController
{
    /**
     * 获取影片列表（支持搜索、分类筛选、排序）
     * @return \think\response\Json
     */
    public function getList()
    {
        try {
            // 获取请求参数
            $params = Request::param();
            $page = isset($params['page']) ? intval($params['page']) : 1;
            $limit = isset($params['limit']) ? intval($params['limit']) : 20;
            $typeId = isset($params['type_id']) ? intval($params['type_id']) : 0;
            $keyword = isset($params['keyword']) ? trim($params['keyword']) : '';
            $sort = isset($params['sort']) ? $params['sort'] : 'latest';
            
            // 参数验证
            if ($page < 1) $page = 1;
            if ($limit < 1 || $limit > 100) $limit = 20;
            
            Log::info('Movie/getList - 开始获取影片列表', [
                'page' => $page,
                'limit' => $limit,
                'type_id' => $typeId,
                'keyword' => $keyword,
                'sort' => $sort
            ]);
            
            // 构建查询
            $query = Db::table('ntp_video')
                ->field('id, video_title, video_img_url, video_describe, type_id, play_times');
            
            // 分类筛选
            if ($typeId > 0) {
                // 检查是否需要包含子分类
                $typeIds = $this->getTypeIdsWithChildren($typeId);
                $query->whereIn('type_id', $typeIds);
                
                Log::debug('Movie/getList - 分类筛选', [
                    'type_id' => $typeId,
                    'type_ids' => $typeIds
                ]);
            }
            
            // 关键词搜索
            if (!empty($keyword)) {
                $query->where('video_title', 'like', '%' . $keyword . '%');
                
                Log::debug('Movie/getList - 关键词搜索', [
                    'keyword' => $keyword
                ]);
            }
            
            // 排序
            switch ($sort) {
                case 'hottest':
                    $query->order('play_times DESC, id DESC');
                    break;
                case 'latest':
                default:
                    $query->order('id DESC');
                    break;
            }
            
            // 获取总数
            $total = Db::table('ntp_video')
                ->where($query->getOptions('where'))
                ->count();
            
            // 分页
            $offset = ($page - 1) * $limit;
            $list = $query->limit($offset, $limit)->select()->toArray();
            
            // 处理返回数据
            foreach ($list as &$item) {
                // 简化视频信息，列表不返回完整的video_info
                unset($item['video_info']);
                
                // 格式化播放次数
                $item['play_times_formatted'] = $this->formatPlayTimes($item['play_times']);
            }
            
            // 获取分类名称
            if (!empty($list)) {
                $typeIds = array_unique(array_column($list, 'type_id'));
                $types = Db::table('ntp_type')
                    ->whereIn('id', $typeIds)
                    ->column('type_name', 'id');
                
                foreach ($list as &$item) {
                    $item['type_name'] = $types[$item['type_id']] ?? '未分类';
                }
            }
            
            Log::info('Movie/getList - 影片列表获取成功', [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'count' => count($list)
            ]);
            
            return $this->success([
                'list' => $list,
                'pagination' => [
                    'total' => $total,
                    'page' => $page,
                    'limit' => $limit,
                    'total_pages' => ceil($total / $limit)
                ]
            ], '获取影片列表成功');
            
        } catch (\Exception $e) {
            Log::error('Movie/getList - 获取影片列表失败', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            return $this->error('获取影片列表失败: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * 获取影片详情
     * @return \think\response\Json
     */
    public function getInfo()
    {
        try {
            $id = Request::param('id', 0, 'intval');
            
            if ($id <= 0) {
                return $this->error('参数错误：影片ID无效', 400);
            }
            
            Log::info('Movie/getInfo - 开始获取影片详情', ['id' => $id]);
            
            // 获取影片信息
            $video = Db::table('ntp_video')
                ->where('id', $id)
                ->find();
            
            if (empty($video)) {
                Log::warning('Movie/getInfo - 影片不存在', ['id' => $id]);
                return $this->error('影片不存在', 404);
            }
            
            // 获取分类名称
            $typeName = Db::table('ntp_type')
                ->where('id', $video['type_id'])
                ->value('type_name');
            
            $video['type_name'] = $typeName ?: '未分类';
            
            // 格式化播放次数
            $video['play_times_formatted'] = $this->formatPlayTimes($video['play_times']);
            
            // 增加播放次数
            Db::table('ntp_video')
                ->where('id', $id)
                ->inc('play_times')
                ->update();
            
            Log::info('Movie/getInfo - 影片详情获取成功', [
                'id' => $id,
                'title' => $video['video_title'],
                'play_times' => $video['play_times'] + 1
            ]);
            
            // 解析video_info（如果是JSON格式的话）
            if (!empty($video['video_info'])) {
                $videoInfo = json_decode($video['video_info'], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $video['video_info'] = $videoInfo;
                }
            }
            
            return $this->success($video, '获取影片详情成功');
            
        } catch (\Exception $e) {
            Log::error('Movie/getInfo - 获取影片详情失败', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            return $this->error('获取影片详情失败: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * 添加播放日志
     * @return \think\response\Json
     */
    public function addPlayLog()
    {
        try {
            $contentId = Request::param('content_id', 0, 'intval');
            
            if ($contentId <= 0) {
                return $this->error('参数错误：内容ID无效', 400);
            }
            
            // 获取用户IP
            $userIp = Request::ip();
            
            // 获取浏览器信息
            $userAgent = Request::header('user-agent', '');
            $referer = Request::header('referer', '');
            
            // 构建info字段
            $info = [
                'user_agent' => $userAgent,
                'referer' => $referer,
                'method' => Request::method(),
                'timestamp' => time()
            ];
            
            Log::info('Movie/addPlayLog - 开始记录播放日志', [
                'content_id' => $contentId,
                'user_ip' => $userIp
            ]);
            
            // 检查影片是否存在
            $exists = Db::table('ntp_video')
                ->where('id', $contentId)
                ->find();
            
            if (empty($exists)) {
                Log::warning('Movie/addPlayLog - 影片不存在', ['content_id' => $contentId]);
                return $this->error('影片不存在', 404);
            }
            
            // 插入播放日志
            $logData = [
                'content_id' => $contentId,
                'user_ip' => $userIp,
                'info' => json_encode($info, JSON_UNESCAPED_UNICODE),
                'creat_time' => date('Y-m-d H:i:s')
            ];
            
            $logId = Db::table('ntp_show_log')->insertGetId($logData);
            
            if ($logId) {
                Log::info('Movie/addPlayLog - 播放日志记录成功', [
                    'log_id' => $logId,
                    'content_id' => $contentId,
                    'user_ip' => $userIp
                ]);
                
                // 同时更新影片播放次数
                Db::table('ntp_video')
                    ->where('id', $contentId)
                    ->inc('play_times')
                    ->update();
                
                return $this->success([
                    'log_id' => $logId,
                    'content_id' => $contentId
                ], '播放日志记录成功');
            } else {
                throw new \Exception('日志记录失败');
            }
            
        } catch (\Exception $e) {
            Log::error('Movie/addPlayLog - 记录播放日志失败', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            return $this->error('记录播放日志失败: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * 获取分类ID及其所有子分类ID
     * @param int $typeId
     * @return array
     */
    private function getTypeIdsWithChildren(int $typeId): array
    {
        $ids = [$typeId];
        
        // 获取所有子分类
        $children = Db::table('ntp_type')
            ->where('pid', $typeId)
            ->column('id');
        
        if (!empty($children)) {
            foreach ($children as $childId) {
                $ids = array_merge($ids, $this->getTypeIdsWithChildren($childId));
            }
        }
        
        return array_unique($ids);
    }
    
    /**
     * 格式化播放次数显示
     * @param int $times
     * @return string
     */
    private function formatPlayTimes(int $times): string
    {
        if ($times < 1000) {
            return (string)$times;
        } elseif ($times < 10000) {
            return round($times / 1000, 1) . 'k';
        } elseif ($times < 1000000) {
            return round($times / 10000, 1) . 'w';
        } else {
            return round($times / 1000000, 1) . 'M';
        }
    }
}