<?php
namespace mia\miagroup\Daemon\Temp;

use mia\miagroup\Data\Subject\Subject as SubjectData;
use mia\miagroup\Data\Koubei\Koubei as KoubeiData;
use mia\miagroup\Service\Subject as SubjectService;
use mia\miagroup\Model\Koubei as KoubeiModel;
use mia\miagroup\Data\Koubei\KoubeiPic as KoubeiPicData;

/**
 * 口碑相关-临时脚本
 * 
 * koubeiSync() 同步过审但没有发蜜芽圈的图片
 * repairSubjectRelation() 修复没有关联蜜芽圈帖子的口碑数据
 * syncKoubeiCommentId() 口碑关联comment_id导入
 * koubeiItemTransfer() 口碑商品迁移
 */
 
class Koubei extends \FD_Daemon {

    private $koubeiModel;
    private $koubeiData;
    private $subjectService;
    private $koubeiPicData;

    public function __construct() {
        $this->koubeiModel = new KoubeiModel();
        $this->koubeiData = new KoubeiData();
        $this->koubeiPicData = new KoubeiPicData();
        $this->subjectService = new SubjectService();
        $this->subjectData = new SubjectData();
    }

    public function execute() {
        $this->koubeiItemTransfer();exit;
        $this->koubeiSync();
    }

    /**
     * 同步过审但没有发蜜芽圈的图片
     */
    public function koubeiSync() {
        // 查出审核到未同步蜜芽贴的口碑
        $koubeiInfos = $this->getKoubeiList();
        // 将审核过的口碑同步到帖子中
        $count = 0;
        foreach ($koubeiInfos as $koubei) {
            $count ++;
            if ($count % 100 == 0) {
                sleep(1);
            }
            // 如果待审核的口碑中不是帖子，则同步到帖子中
            // 发口碑同时发布蜜芽圈帖子
            $subjectInfo = array();
            $subjectInfo['user_info']['user_id'] = $koubei['user_id'];
            $subjectInfo['title'] = $koubei['title'];
            $subjectInfo['text'] = $koubei['content'];
            $subjectInfo['created'] = $koubei['created_time'];
            $subjectInfo['ext_info'] = json_decode($koubei['extr_info']);
            // 获取口碑图片信息
            $imageInfos = array();
            $koubeiPic = $this->getKoubeiPic($koubei['id']);
            if (!empty($koubeiPic)) {
                $i = 0;
                foreach ($koubeiPic as $pic) {
                    $imageInfos[$i]['url'] = $pic['local_url_origin'];
                    $size = getimagesize("http://img.miyabaobei.com/" . $pic['local_url_origin']);
                    $imageInfos[$i]['width'] = $size[0];
                    $imageInfos[$i]['height'] = $size[1];
                    $i ++;
                }
            }
            $subjectInfo['image_infos'] = $imageInfos;
            $labelInfos = array();
            
            if (!empty($subjectInfo['ext_info']->label)) {
                $labels = $subjectInfo['ext_info']->label;
                foreach ($labels as $label) {
                    $labelInfos[] = array('title' => $label);
                }
            }
            $pointInfo[0] = array('item_id' => $koubei['item_id']);
            $subjectIssue = $this->subjectService->issue($subjectInfo, $pointInfo, $labelInfos, $koubei['id'])['data'];
            // 将帖子id回写到口碑表中
            if (!empty($subjectIssue) && $subjectIssue['id'] > 0) {
                $this->koubeiModel->addSubjectIdToKoubei($koubei['id'], $subjectIssue['id']);
            }
            echo "subject_id: {$subjectIssue['id']}   koubeiId: {$koubei['id']}  \n";
        }
    }

    /**
     * 修复没有关联蜜芽圈帖子的口碑数据
     */
    public function repairSubjectRelation() {
        $startDate = '2016-08-30';
        $endDate = '2016-09-01';
        
        $where = array();
        $where[] = array(':gt', 'created', $startDate);
        $where[] = array(':lt', 'created', $endDate);
        $subjects = $this->subjectData->getRows($where, 'id, ext_info');
        
        $i = 0;
        foreach ($subjects as $subject) {
            $i ++;
            if ($i % 100 == 0) {
                sleep(1);
            }
            $koubeiId = json_decode($subject['ext_info'], true);
            if (isset($koubeiId['koubei']['id'])) {
                $koubeiId = $koubeiId['koubei']['id'];
            } else {
                $koubeiId = 0;
            }
            
            if (intval($koubeiId) > 0) {
                $where = array();
                $where[] = array(':eq', 'id', $koubeiId);
                $koubeiInfo = $this->koubeiData->getRow($where, 'id, subject_id');
                if (intval($koubeiInfo['subject_id']) == 0) {
                    echo "subject_id: {$subject['id']}   koubeiId: {$koubeiId}  \n";
                    $this->koubeiData->updateKoubeiBySubjectid($koubeiId, $subject['id']);
                }
            }
        }
    }

    /**
     * 获取已经审核的但未同步为蜜芽贴的口碑信息
     */
    private function getKoubeiList() {
        $where = array();
        $where[] = [':eq', 'subject_id', 0];
        $where[] = [':eq', 'status', 2];
        $where[] = [':ge', 'created_time', '2016-08-29 00:00:00'];
        
        $fields = ' id,subject_id,user_id,item_id,title,content,score,created_time,extr_info ';
        $data = $this->koubeiData->getRows($where, $fields);
        return $data;
    }
    
    /**
     * 获取审核通过的口碑
     */
    private function getPassKoubeiList() {
        $where = array();
        $where[] = [':ne', 'subject_id', 0];
        $where[] = [':eq', 'status', 2];
        
        $fields = ' id,subject_id,user_id,item_id,title,content,score,created_time,extr_info ';
        $data = $this->koubeiData->getRows($where, $fields);
        return $data;
    }

    /**
     * 获取口碑图片信息
     */
    private function getKoubeiPic($koubeiId) {
        $where = array();
        $where[] = [':eq', 'koubei_id', $koubeiId];
        
        $fields = ' id,koubei_id,local_url_origin ';
        $data = $this->koubeiPicData->getRows($where, $fields);
        return $data;
    }
    
    /**
     * 口碑关联comment_id导入
     */
    public function syncKoubeiCommentId() {
        //获取需要关联口碑
        $where = array();
        $where[] = [':gt', 'comment_supplier_id', 0];
        $where[] = [':gt', 'subject_id', 0];
        $fields = ' id, subject_id, supplier_id ';
        $data = $this->koubeiData->getRows($where, $fields);
        
        $mappingData = new \mia\miagroup\Data\Item\UserSupplierMapping();
        $commentData = new \mia\miagroup\Data\Comment\SubjectComment();
        foreach ($data as $v) {
            $mapping = $mappingData->getMappingBySupplierId($v['supplier_id']);
            if (empty($mapping)) {
                continue;
            }
            //获取待导入的comment_id
            $user_id = $mapping['user_id'];
            $cond = array();
            $cond['subject_id'] = ['subject_id', $v['subject_id']];
            $cond['user_id'] = ['user_id', $user_id];
            $comment_id = $commentData->getCommentListByCond($cond, 0, 1, 'id desc');
            if (empty($comment_id)) {
                continue;
            }
            $comment_id = reset($comment_id);
            $comment_id = $comment_id['id'];
            //update comment_id
            $this->koubeiData->updateKoubeiInfoById($v['id'], [['comment_id', $comment_id]]);
        }
    }
    
    public function koubeiItemTransfer() {
        //读取待迁移的itemlist
        $data = file('/tmp/koubei_item_transfer');
        $koubeiData = new KoubeiData();
        $koubeiTagData = new \mia\miagroup\Data\Koubei\KoubeiTagsRelation();
        $pointTagData = new \mia\miagroup\Data\PointTags\SubjectPointTags();
        foreach ($data as $v) {
            $v = trim($v);
            list($origin_item_id, $new_item_id) = explode("\t", $v);
            //更新koubei表
            $koubeiData->update([['item_id', $new_item_id]], [['item_id', $origin_item_id]]);
            //更新koubei_tags_relation表
            $koubeiTagData->update([['item_id', $new_item_id]], [['item_id', $origin_item_id]]);
            //更新group_subject_point_tags表
            $pointTagData->update([['item_id', $new_item_id]], [['item_id', $origin_item_id], ['type', 'sku']]);
            exit;
        }
    }
}