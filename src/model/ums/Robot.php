<?php
namespace mia\miagroup\Model\Ums;

use Ice;

class Robot extends \DB_Query {

    protected $dbResource = 'miagroupums';
    //头像素材表
    protected $tableAvatarMaterial = 'group_robot_avatar_material';
    //帖子素材表
    protected $tableSubjectMaterial = 'group_robot_subject_materials';
    //编辑帖子表
    protected $tableEditorSubject = 'group_robot_editor_subject';
    //文本素材表
    protected $tableTextMaterial = 'group_robot_text_material';
    
    /**
     * 查询帖子素材表
     */
    public function getSubjectMaterialData($cond, $offset = 0, $limit = 10, $orderBy = '') {
        $this->tableName = $this->tableSubjectMaterial;
        $result = array('count' => 0, 'list' => array());
        $where = array();
        if (!empty($cond)) {
            //组装where条件
            foreach ($cond as $k => $v) {
                switch ($k) {
                    default:
                        $where[] = [$k, $v];
                }
            }
        }
        $result['count'] = $this->count($where);
        if (intval($result['count']) <= 0) {
            return $result;
        }
        $data = $this->getRows($where, 'id', $limit, $offset, $orderBy);
        if (!empty($data)) {
            foreach ($data as $v) {
                $result['list'][] = $v['id'];
            }
        }
        return $result;
    }
    
    /**
     * 查询头像素材表
     */
    public function getAvatarMaterialData($cond, $offset = 0, $limit = 50, $orderBy = '') {
        $this->tableName = $this->tableAvatarMaterial;
        $result = array('count' => 0, 'list' => array());
        $where = array();
        if (!empty($cond)) {
            //组装where条件
            foreach ($cond as $k => $v) {
                switch ($k) {
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
        return $result;
    }
    
    /**
     * 查询编辑帖子列表
     */
    public function getEditorSubjectData($cond, $offset = 0, $limit = 50, $orderBy = '') {
        $this->tableName = $this->tableEditorSubject;
        $result = array('count' => 0, 'list' => array());
        $where = array();
        if (!empty($cond)) {
            //组装where条件
            foreach ($cond as $k => $v) {
                switch ($k) {
                    case 'create_start_time':
                        $where[] = [':ge','create_time', $v];
                        break;
                    case 'create_end_time':
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
        return $result;
    }
    
    /**
     * 查询文本素材表
     */
    public function getTextMaterialData($cond, $offset = 0, $limit = 50, $orderBy = '') {
        $this->tableName = $this->tableTextMaterial;
        $result = array('count' => 0, 'list' => array());
        $where = array();
        if (!empty($cond)) {
            //组装where条件
            foreach ($cond as $k => $v) {
                switch ($k) {
                    case 'text':
                        $where[] = [':like_literal','text', "%$v%"];
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
        return $result;
    }
    
    /**
     * 查询帖子素材抓取来源
     */
    public function getSubjectMaterialSource() {
        $this->tableName = $this->tableSubjectMaterial;
        $sql = "SELECT DISTINCT(`source`) from {$this->tableName}";
        $data = $this->query($sql);
        if (!empty($data)) {
            foreach ($data as $v) {
                $result[] = $v['source'];
            }
        }
        return $result;
    }
    
    /**
     * 查询帖子素材抓取来源
     */
    public function getSubjectMaterialCategory() {
        $this->tableName = $this->tableSubjectMaterial;
        $sql = "SELECT DISTINCT(`category`) from {$this->tableName}";
        $data = $this->query($sql);
        if (!empty($data)) {
            foreach ($data as $v) {
                $result[] = $v['category'];
            }
        }
        return $result;
    }
}