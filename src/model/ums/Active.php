<?php
namespace mia\miagroup\Model\Ums;

use Ice;

class Active extends \DB_Query {

    protected $dbResource = 'miagroupums';

    protected $tableActive = 'group_active';
    protected $tableActiveSubjectRelation = 'group_subject_active_relation';
    protected $tableSubjectPointTags = 'group_subject_point_tags';

    public function getGroupActiveData($month)
    {
        $this->tableName = $this->tableActive;
        $time = date("Y-m-d", strtotime("-".$month." month"));
        $where[] = ['status',1];
        $field = "id, title";
        $where[] = [':gt', 'created', $time];
        $res = $this->getRows($where, $field);
        return $res;
    }
    
    /**
     * 获取活动图片列表
     */
    public function getActiveSubjectList($cond, $offset = 0, $limit = 50, $orderBy = '') {
        $this->tableName = $this->tableActiveSubjectRelation;
        $result = array('count' => 0, 'list' => array());
        $where = array();
        if (!empty($cond)) {
            //组装where条件
            foreach ($cond as $k => $v) {
                switch ($k) {
                    case 'start_time':
                        $where[] = [':ge','create_time', $v];
                        break;
                    case 'end_time':
                        $where[] = [':le','create_time', $v];
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
                $result['list'][$k] = $v;
            }
        }
        return $result;
    }
    
    /**
     * 根据商品id获取参加活动的帖子
     */
    public function getActiveSubjectByItem($cond, $offset = 0, $limit = 50, $orderBy = 'id desc') {
        $this->tableName = $this->tableActiveSubjectRelation;
        $orderBy = $this->tableName. '.id desc';
        $result = array('count' => 0, 'list' => array());
        $where = array();
        if (!empty($cond)) {
            //组装where条件
            foreach ($cond as $k => $v) {
                switch ($k) {
                    case 'start_time':
                        $where[] = [':ge','create_time', $v];
                        break;
                    case 'end_time':
                        $where[] = [':le','create_time', $v];
                        break;
                    case 'item_id':
                        $where[] = ['pt.item_id', $v];
                    default:
                        $where[] = [$k, $v];
                }
            }
        }
        
        $join = 'left join '.$this->tableSubjectPointTags. ' as pt on ' .$this->tableName . '.subject_id=pt.subject_id ';
        $field = 'distinct '. $this->tableName. '.subject_id';
        $result['count'] = $this->count($where, $join, $field);
        
        if (intval($result['count']) <= 0) {
            return $result;
        }

        $result['list'] = $this->getRows($where, $field, $limit, $offset, $orderBy, $join);
        return $result;
    }
}