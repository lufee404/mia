<?php
namespace mia\miagroup\Model\Ums;

use Ice;

class Subject extends \DB_Query {

    protected $dbResource = 'miagroupums';
    //帖子
    protected $tableSubject = 'group_subjects';
    protected $indexSubject = array('id', 'user_id', 'created','status');

    /**
     * 查询口碑表数据
     */
    public function getSubjectData($cond, $offset = 0, $limit = 50, $orderBy = '') {
        $this->tableName = $this->tableSubject;
        $result = array('count' => 0, 'list' => array());
        $where = array();
        if (!empty($cond)) {
            //检查是否使用索引，没有索引强制加
            if (empty(array_intersect(array_keys($cond), $this->indexSubject))) {
                $where[] = [':ge','created', date('Y-m-d H:i:s', time() - 86400 * 90)];
            }
            //组装where条件
            foreach ($cond as $k => $v) {
                switch ($k) {
                    case 'start_time':
                        $where[] = [':ge','created', $v];
                        break;
                    case 'end_time':
                        $where[] = [':le','created', $v];
                        break;
                    default:
                        $where[] = [$k, $v];
                }
            }
        }
        $result['count'] = $this->count($where);
        if (intval($result['count']) <= 0) {
            return $result;
        }
        $result['list'] = $this->getRows($where, '*', $limit, $offset, $orderBy);
        if (!empty($result['list'])) {
            foreach ($result['list'] as $k => $v) {
                $result['list'][$k]['subject_id'] = $v['id'];
            }
        }
        return $result;
    }
    
    /**
     * 帖子置顶
     */
    public function setSubjectTopStatus($subjectIds,$status=1){
        $setData[] = ['is_top',$status];
        $setData[] = ['top_time','now()'];
        $where[] = ['id',$subjectIds];
        $affect = $this->update($setData,$where);
        return $affect;
    }
    
    /**
     * 批量屏蔽帖子
     */
    public function shieldSubject($subjectIds,$shieldReason){
        $setData[] = ['shield_text',$shieldReason];
        $setData[] = ['status',-1];
        $where[]   = ['id',$subjectIds];
        $affect = $this->update($setData,$where);
        return $affect;
    }
    
    /**
     * 批量解除帖子
     */
    public function relieveShieldSubject($subjectIds,$status=1){
        $setData[] = ['status',$status];
        $where[]   = ['id',$subjectIds];
        $affect = $this->update($setData,$where);
        return $affect;
    }
    
    
}