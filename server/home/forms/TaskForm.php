<?php
namespace xoa\home\forms;

use xoa\common\models\{
	Project,
	Task,
	Worker
};
use xoa\home\models\TaskCategory;
use yii\helpers\ArrayHelper;

/**
 * 任务表单
 * @author KK
 */
class TaskForm extends \yii\base\Model{
	/**
	 * 场景：添加任务
	 */
	const SCENE_ADD = 'add';
	/**
	 * 场景：任务列表
	 */
	const SCENE_LIST = 'list';
	/**
	 * @var string 任务标题
	 */
	public $title = '';
	
	/**
	 * @var string 任务详情
	 */
	public $detail = '';
	
	/**
	 * @var int 任务分类ID
	 */
	public $taskCategoryId = 0;
	
	/**
	 * @var array 负责人ID集合
	 */
	public $workerIds = '';
	
	/**
	 * @var array 相关人员ID集合
	 */
	public $relatedMemberIds = '';
	
	/**
	 * @var array 负责人集合
	 */
	public $workers = '';
	
	/**
	 * @var array 相关人员集合
	 */
	public $relatedMembers = '';
	
	/**
	 * @var string 限制完成时间
	 */
	public $limitTime = '';
	
	/**
	 * @var TaskCategory 任务分类
	 */
	private $_taskCategory = null;
	
	/**
	 * @inheritedoc
	 * @author KK
	 */
	public function rules(){
		return [
			[['title', 'workerIds'], 'required'],
			['title', 'string', 'length' => [4, 30], 'message' => '任务标题在4到30个字之间'],
			['detail', 'string', 'length' => [4, 65535], 'message' => '任务详情在4到65535个字之间'],
			[['workerIds', 'relatedMemberIds'], 'each', 'rule' => ['integer']],
			[['workerIds', 'relatedMemberIds'], 'validateMemberIds'],
			['taskCategoryId', 'validateCategoryId'],
			['limitTime', 'validateLimitTime'],
		];
	}
	
	/**
	 * @inheritedoc
	 * @author KK
	 */
	public function scenarios() {
		return [
			static::SCENE_ADD => ['title', 'detail', 'taskCategoryId', 'workerIds', 'relatedMemberIds', 'limitTime'],
			static::SCENE_LIST => ['taskCategoryId'],
		];
	}
	
	/**
	 * 验证成员ID集
	 * @author KK
	 * @param string $attributeName 被验证的属性名称
	 * @param array $params 验证的附加参数
	 */
	public function validateMemberIds($attributeName, $params){
		$workers = Worker::findAll($this->{$attributeName});
		if(!$workers){
			$role = $attributeName == 'workerIds' ? '负责人' : '成员';
			$this->addError($attributeName, '无效的' . $role . 'ID');
		}else{
			$setAttr = $attributeName == 'workerIds' ? 'workers' : 'relatedMembers';
			$this->{$setAttr} = $workers;
		}
	}
	
	/**
	 * 验证任务分类ID
	 */
	public function validateCategoryId(){
		if(!$taskCategory = TaskCategory::findOne($this->taskCategoryId)){
			return $this->addError('taskCategoryId', '无效的任务分类');
		}
		$this->_taskCategory = $taskCategory;
	}
	
	/**
	 * 验证限制完成时间
	 */
	public function validateLimitTime(){
		$time = strtotime($this->limitTime);
		if($time < time()){
			return $this->addError('limitTime', '完成时间必须是未来的时间点');
		}
		$this->limitTime = date('Y-m-d H:i', $time);
	}
	
	
	
	/**
	 * 获取任务列表
	 * @author KK
	 * @return Task
	 * @throws \yii\base\ErrorException
	 * @test \xoa_test\home\unit\TaskTest::testAdd
	 */
	public function add(){
		if(!$this->validate()){
			return null;
		}
		
		$task = new Task([
			'project_id' => $this->_taskCategory->project->id,
			'task_category_id' => $this->_taskCategory->id,
			'title' => $this->title,
			'detail' => $this->detail,
			'worker_ids' => implode(',', $this->workerIds),
			'limit_time' => $this->limitTime,
			'add_time' => date('Y-m-d H:i:'),
		]);
		if($this->relatedMemberIds){
			$task->related_member_ids = implode(',', $this->relatedMemberIds);
		}
		
		if($task->save()){
			return $task;
		}else{
			throw new \yii\base\ErrorException('添加任务失败');
		}
	}
	
	/**
	 * 获取任务列表
	 * @author KK
	 * @test \xoa_test\home\unit\TaskTest::testList
	 * @return array
	 */
	public function getList(){
		if(!$this->validate()){
			return false;
		}
		
		$tasks = Task::findAll([
			'project_id' => $this->_taskCategory->project->id,
			'task_category_id' => $this->_taskCategory->id,
		]);
		$result = [];
		foreach($tasks as $task){
			$item = $task->toArray(['id', 'title', 'limit_time']);
			$item['workers'] = [];
			foreach ($task->workers as $worker) {
				$item['workers'][] = $worker->toArray(['name', 'avatar']);
			}
			$result[] = $item;
		}
		return $result;
	}
}