<?php
namespace app\controller\movie;

use app\BaseController;
use think\facade\Db;
use think\facade\Log;

class Type extends BaseController
{
    /**
     * 获取分类列表
     * @return \think\response\Json
     */
    public function getList()
    {
        try {
            Log::info('Type/getList - 开始获取分类列表');
            
            // 获取所有分类数据
            $types = Db::table('ntp_type')
                ->field('id, pid, type_name')
                ->order('pid ASC, id ASC')
                ->select()
                ->toArray();
            
            Log::debug('Type/getList - 查询到分类数量: ' . count($types));
            
            // 构建树形结构
            $tree = $this->buildTypeTree($types);
            
            // 统计每个分类下的视频数量
            $tree = $this->countVideos($tree);
            
            Log::info('Type/getList - 分类列表获取成功', [
                'total_count' => count($types),
                'tree_count' => count($tree)
            ]);
            
            return $this->success($tree, '获取分类列表成功');
            
        } catch (\Exception $e) {
            Log::error('Type/getList - 获取分类列表失败', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            return $this->error('获取分类列表失败: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * 构建分类树形结构
     * @param array $types 分类数组
     * @param int $pid 父级ID
     * @return array
     */
    private function buildTypeTree(array $types, int $pid = 0): array
    {
        $tree = [];
        
        foreach ($types as $type) {
            if ($type['pid'] == $pid) {
                $children = $this->buildTypeTree($types, $type['id']);
                
                $node = [
                    'id' => $type['id'],
                    'name' => $type['type_name'],
                    'pid' => $type['pid']
                ];
                
                if (!empty($children)) {
                    $node['children'] = $children;
                }
                
                $tree[] = $node;
            }
        }
        
        return $tree;
    }
    
    /**
     * 统计每个分类下的视频数量
     * @param array $tree 分类树
     * @return array
     */
    private function countVideos(array $tree): array
    {
        foreach ($tree as &$node) {
            // 获取当前分类的视频数量
            $count = Db::table('ntp_video')
                ->where('type_id', $node['id'])
                ->count();
            
            $node['video_count'] = $count;
            
            // 如果有子分类，递归统计
            if (isset($node['children']) && !empty($node['children'])) {
                $node['children'] = $this->countVideos($node['children']);
                
                // 累加子分类的视频数量
                foreach ($node['children'] as $child) {
                    $node['video_count'] += $child['video_count'];
                }
            }
        }
        
        return $tree;
    }
}