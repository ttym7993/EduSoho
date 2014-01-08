<?php
namespace Topxia\WebBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Topxia\Common\ArrayToolkit;
use Topxia\Common\Paginator;

class QuizQuestionTestController extends BaseController
{
	public function indexAction(Request $request, $courseId)
	{
		$course = $this->getCourseService()->tryManageCourse($courseId);
		$lessons = $this->getCourseService()->getCourseLessons($courseId);

		$parentId = $request->query->get('parentId');

		$conditions['target']['course'] = array($courseId);
		if (!empty($lessons)){
			$conditions['target']['lesson'] = ArrayToolkit::column($lessons,'id');;
		}

		$paginator = new Paginator(
			$this->get('request'),
			$this->getTestService()->searchTestPaperCount($conditions),
			10
		);

		$testPapers = $this->getTestService()->searchTestPaper(
			$conditions,
			array('createdTime' ,'DESC'),
			$paginator->getOffsetCount(),
            $paginator->getPerPageCount()
		);

		$lessons = ArrayToolkit::index($lessons,'id');
		$users = $this->getUserService()->findUsersByIds(ArrayToolkit::column($testPapers, 'updatedUserId')); 
		
		return $this->render('TopxiaWebBundle:QuizQuestionTest:index.html.twig', array(
			'course' => $course,
			'testPapers' => $testPapers,
			'users' => $users,
			'lessons' => $lessons,
			'paginator' => $paginator,

		));
	}

	public function createAction(Request $request, $courseId)
	{
		$course = $this->getCourseService()->tryManageCourse($courseId);

		$testPaper = $request->query->all();

	    if ($request->getMethod() == 'POST') {
	    	$testPaper = $request->request->all();

        	if(empty($testPaper['testPaperId'])){
	        	$result = $this->getTestService()->createTestPaper($testPaper);
        	}else{
	        	$result = $this->getTestService()->createUpdateTestPaper($testPaper['testPaperId'], $testPaper);
        	}

	        $testPaper['courseId'] = $courseId;
	        $testPaper['testPaperId'] = $result['id'];
			return $this->redirect($this->generateUrl('course_manage_test_paper_create_two',$testPaper));
        }

        if(empty($testPaper['target'])){
			$testPaper['target']  = 'course-'.$courseId;
		}

        if(!empty($testPaper['testPaperId'])){
			$paper = $this->getTestService()->getTestPaper($testPaper['testPaperId']);
			$testPaper = array_merge($testPaper, $paper);
		}

		return $this->render('TopxiaWebBundle:QuizQuestionTest:create-1.html.twig', array(
			'course'    => $course,
			'testPaper' => $testPaper,
		));
	}

	public function updateAction(Request $request, $courseId)
	{
		$course = $this->getCourseService()->tryManageCourse($courseId);

		$testPaper = $request->query->all();

	    if ($request->getMethod() == 'POST') {

	    	$testPaper = $request->request->all();
	        $result = $this->getTestService()->updateTestPaper($testPaper['testPaperId'], $testPaper);

	        $this->setFlashMessage('success', '试卷修改成功！');

			return $this->redirect($this->generateUrl('course_manage_test_paper',array( 'courseId' => $courseId)));
        }

        if(empty($testPaper['target'])){
			$testPaper['target']  = 'course-'.$courseId;
		}

        if(!empty($testPaper['testPaperId'])){
			$paper = $this->getTestService()->getTestPaper($testPaper['testPaperId']);
			$testPaper = array_merge($testPaper, $paper);
		}

		return $this->render('TopxiaWebBundle:QuizQuestionTest:update-1.html.twig', array(
			'course'    => $course,
			'testPaper' => $testPaper,
		));
	}

	public function updateResetAction(Request $request, $courseId)
	{
		$course = $this->getCourseService()->tryManageCourse($courseId);
		$testPaper = $request->query->all();

		if(empty($testPaper['testPaperId'])){
			throw $this->createNotFoundException('缺少参数');
		}

		$this->getTestService()->deleteItemsByTestPaperId($testPaper['testPaperId']);

	    if ($request->getMethod() == 'POST') {

	    	$testPaper = $request->request->all();

			$testPaper['courseId']    = $courseId;
			$testPaper['testPaperId'] = $testPaper['testPaperId'];
			$testPaper['flag']        = 'reset';

			return $this->redirect($this->generateUrl('course_manage_test_paper_create_two',$testPaper));
        }

        if(empty($testPaper['target'])){
			$testPaper['target']  = 'course-'.$courseId;
		}

        if(!empty($testPaper['testPaperId'])){
			$paper = $this->getTestService()->getTestPaper($testPaper['testPaperId']);
			$testPaper = array_merge($testPaper, $paper);
		}

		return $this->render('TopxiaWebBundle:QuizQuestionTest:update-reset.html.twig', array(
			'course'    => $course,
			'testPaper' => $testPaper,
		));
	}

	public function createTwoAction(Request $request, $courseId, $testPaperId)
	{
		$course = $this->getCourseService()->tryManageCourse($courseId);

		$testPaper = $this->getTestService()->getTestPaper($testPaperId);
		if(empty($testPaper)){
			throw $this->createNotFoundException('试卷不存在');
		}

		$flag = $request->query->get('flag');
		$flag   = empty($flag) ? null : $flag;

		if ($request->getMethod() == 'POST') {

	    	$field    = $request->request->all();

	    	if ($flag = 'update'){

        		$this->getTestService()->updateItems($testPaperId, $field);
	    	} else {

        		$this->getTestService()->createItems($testPaperId, $field);
	    	}

	    	$this->setFlashMessage('success', '试卷题目保存成功！');

        	return $this->redirect($this->generateUrl('course_manage_test_paper',array( 'courseId' => $courseId)));
        }

		$parentTestPaper  = array_merge($request->query->all(), $testPaper);

		$dictQuestionType = $this->getWebExtension()->getDict('questionType');

		$lessons          = ArrayToolkit::index($this->getCourseService()->getCourseLessons($courseId),'id');

		$items = array();
		if($flag == 'update'){

			$items     = ArrayToolkit::index($this->getTestService()->findItemsByTestPaperId($testPaperId), 'questionId');
		    $questions = ArrayToolkit::index($this->getQuestionService()->findQuestionsByIds(ArrayToolkit::column($items, 'questionId')), 'id');
		} else {

			$questions = $this->getQuestionService()->findRandQuestions($courseId, $testPaperId, $parentTestPaper);
		}

		return $this->render('TopxiaWebBundle:QuizQuestionTest:create-2.html.twig', array(
			'course' => $course,
			'items' => $items,
			'questions' => $questions,
			'testPaper' => $testPaper,
			'parentTestPaper' => $parentTestPaper,
			'lessons' => $lessons,
			'flag' => $flag,
		));
	}

	public function itemListAction(Request $request, $courseId,  $testPaperId)
	{
		$replaceId = $request->query->get('testItemId');

		$type = $request->query->get('type');
		$ids = $request->query->get('ids');

        if(empty($type)){
            throw $this->createNotFoundException('type 参数不对');
        }

		$type = explode('-', $type);

		$course    = $this->getCourseService()->tryManageCourse($courseId);
		$lessons   = $this->getCourseService()->getCourseLessons($courseId);
		$testPaper = $this->getTestService()->getTestPaper($testPaperId);
		$itemIds   = ArrayToolkit::column($this->getTestService()->findItemsByTestPaperIdAndQuestionType($testPaperId, $type), 'questionId');

        $conditions['target']['course'] = array($courseId);
        if (!empty($lessons)){
            $conditions['target']['lesson'] = ArrayToolkit::column($lessons,'id');;
        }

        $conditions['parentId'] = 0;
        $conditions['notId']    = explode(',', $ids);
        $conditions[$type['0']] = $type['1'];

        $paginator = new Paginator(
			$this->get('request'),
			$this->getQuestionService()->searchQuestionCount($conditions),
			5
		);

        $questions = $this->getQuestionService()->searchQuestion(
        		$conditions, 
        		array('createdTime' ,'DESC'), 
        		$paginator->getOffsetCount(),
                $paginator->getPerPageCount()
        );

		$lessons = ArrayToolkit::index($lessons,'id');
		$users = $this->getUserService()->findUsersByIds(ArrayToolkit::column($questions, 'userId')); 

		return $this->render('TopxiaWebBundle:QuizQuestionTest:create-list.html.twig', array(
			'course' => $course,
			'lessons' => $lessons,
			'questions' => $questions,
			'testPaper' => $testPaper,
			'parentId' => false,
			'users' => $users,
			'paginator' => $paginator,
			'replaceId' => $replaceId
		));
	}

	public function createItemActionss(Request $request, $courseId,  $testPaperId)
	{
		$questionId = $request->query->get('questionId');
		$replaceId  = $request->query->get('replaceId');

		$course    = $this->getCourseService()->tryManageCourse($courseId);
		$lessons   = ArrayToolkit::index($this->getCourseService()->getCourseLessons($courseId), 'id');

		$question = ArrayToolkit::index($this->getQuestionService()->getQuestion($questionId), 'id');
		
		$testPaper = $this->getTestService()->getTestPaper($testPaperId);

		if (!empty($replaceId)){
			$item = $this->getTestService()->updateItem($replaceId, $questionId);
		} else {
			$item = $this->getTestService()->createItem($testPaperId, $questionId);
		}
        
		return $this->render('TopxiaWebBundle:QuizQuestionTest:create-2-tr.html.twig', array(
			'course' => $course,
			'testPaperId' => $testPaperId,
			'item' => $item,
			'questions' => $questions,
			'testPaper' => $testPaper,
			'lessons' => $lessons,
		));
	}

	public function createItemAction(Request $request, $courseId,  $testPaperId)
	{
		$questionId = $request->query->get('questionId');
		$replaceId  = $request->query->get('replaceId');

		$course    = $this->getCourseService()->tryManageCourse($courseId);
		$lessons   = ArrayToolkit::index($this->getCourseService()->getCourseLessons($courseId), 'id');
	
		$testPaper = $this->getTestService()->getTestPaper($testPaperId);

		$questions = $this->getQuestionService()->findQuestionsByParentIds(array($questionId));

		$questions[] = $this->getQuestionService()->getQuestion($questionId);

		return $this->render('TopxiaWebBundle:QuizQuestionTest:create-2-tbody.html.twig', array(
			'course' => $course,
			'testPaper' => $testPaper,
			'questions' => $questions,
			'lessons' => $lessons,
		));
	}

	public function quesitonNumberCheckAction(Request $request, $courseId)
    {
		$course = $this->getCourseService()->tryManageCourse($courseId);

		$field = $request->request->all();

        $field['questionType'] = $this->getWebExtension()->getDict('questionType');

        $field['difficulty']   = $this->getWebExtension()->getDict('difficulty');

        return $this->createJsonResponse($this->getQuestionService()->checkQuesitonNumber($field, $courseId));
    }

    public function deleteItemAction(Request $request, $courseId, $testItemId)
    {
		$course = $this->getCourseService()->tryManageCourse($courseId);

        $item = $this->getTestService()->getTestItem($testItemId);

        if (empty($item)) {
            throw $this->createNotFoundException();
        }

        $this->getTestService()->deleteItem($testItemId);

        return $this->createJsonResponse(true);
    }

    public function deleteItemsAction(Request $request, $courseId)
    {   
		$course = $this->getCourseService()->tryManageCourse($courseId);

        $ids = $request->request->get('ids');

        if(empty($ids)){
        	throw $this->createNotFoundException();
        }

        foreach ($ids as $id) {
        	$this->getTestService()->deleteItem($id);
        }

        return $this->createJsonResponse(true);
    }



	private function getCourseService()
    {
        return $this->getServiceKernel()->createService('Course.CourseService');
    }

   	private function getQuestionService()
   	{
   		return $this -> getServiceKernel()->createService('Quiz.QuestionService');
   	}

   	private function getTestService()
   	{
   		return $this -> getServiceKernel()->createService('Quiz.TestService');
   	}

   	private function getWebExtension()
    {
        return $this->container->get('topxia.twig.web_extension');
    }

}