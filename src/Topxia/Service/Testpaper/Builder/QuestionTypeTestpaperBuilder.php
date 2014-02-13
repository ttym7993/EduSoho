<?php
namespace Topxia\Service\Testpaper\Builder;

use Topxia\Service\Common\BaseService;
use Topxia\Service\Testpaper\TestpaperBuilder;
use Topxia\Common\ArrayToolkit;

class QuestionTypeTestpaperBuilder extends BaseService implements TestpaperBuilder
{

    public function build($testpaper, $options)
    {
        $questions = $this->getQuestions($options);
        shuffle($questions);
        $typedQuestions = ArrayToolkit::group($questions, 'type');

        $canBuildResult = $this->canBuildWithQuestions($options, $typedQuestions);
        if ($canBuildResult['status'] == 'no') {
            return array('status' => 'error', 'missing' => $canBuildResult['missing']);
        }

        $items = array();
        foreach ($options['counts'] as $type => $needCount) {
            $needCount = intval($needCount);
            if ($needCount == 0) {
                continue;
            }

            if ($options['mode'] == 'difficulty') {
                $difficultiedQuestions = ArrayToolkit::group($typedQuestions[$type], 'difficulty');

                // 按难度百分比选取Question
                $selectedQuestions = $this->selectQuestionsWithDifficultlyPercentage($difficultiedQuestions, $needCount, $options['percentages']);

                // 选择的Question不足的话，补足
                $selectedQuestions = $this->fillQuestionsToNeedCount($selectedQuestions, $typedQuestions[$type], $needCount);

                $itemsOfType = $this->convertQuestionsToItems($testpaper, $selectedQuestions, $needCount, $options['scores'][$type]);
            } else {
                $itemsOfType = $this->convertQuestionsToItems($testpaper, $typedQuestions[$type], $needCount, $options['scores'][$type]);
            }
            $items = array_merge($items, $itemsOfType);
        }

        return array('status' => 'ok', 'items' => $items);
    }

    public function canBuild($options)
    {
        $questions = $this->getQuestions($options);
        $typedQuestions = ArrayToolkit::group($questions, 'type');
        return $this->canBuildWithQuestions($options, $typedQuestions);
    }

    private function fillQuestionsToNeedCount($selectedQuestions, $allQuestions, $needCount)
    {
        $indexedQuestions = ArrayToolkit::index($allQuestions, 'id');
        foreach ($selectedQuestions as $question) {
            unset($indexedQuestions[$question['id']]);
        }

        if (count($selectedQuestions) < $needCount) {
            $stillNeedCount = $needCount - count($selectedQuestions);
        }

        $questions = array_slice(array_values($indexedQuestions), 0, $stillNeedCount);
        $selectedQuestions = array_merge($selectedQuestions, $questions);

        return $selectedQuestions;
    }

    private function selectQuestionsWithDifficultlyPercentage($difficultiedQuestions, $needCount, $percentages)
    {
        $selectedQuestions = array();
        foreach ($percentages as $difficulty => $percentage) {
            $subNeedCount = intval($needCount * $percentage / 100);
            if ($subNeedCount == 0) {
                continue;
            }

            $questions = array_slice($difficultiedQuestions[$difficulty], 0, $subNeedCount);
            $selectedQuestions = array_merge($selectedQuestions, $questions);
        }

        return $selectedQuestions;
    }

    private function canBuildWithQuestions($options, $questions)
    {
        $missing = array();

        foreach ($options['counts'] as $type => $needCount) {
            $needCount = intval($needCount);
            if ($needCount == 0) {
                continue;
            }

            if (empty($questions[$type])) {
                $missing[$type] = $needCount;
                continue;
            }

            if (count($questions[$type]) < $needCount) {
                $missing[$type] = $needCount - count($questions[$type]);
            }
        }

        if (empty($missing)) {
            return array('status' => 'yes');
        }

        return array('status' => 'no', 'missing' => $missing);
    }

    private function getQuestions($options)
    {
        $conditions = array();

        if (!empty($options['ranges'])) {
            $conditions['targets'] = $options['ranges'];
        } else {
            $conditions['targetPrefix'] = $options['target'];
        }

        $conditions['parentId'] = 0;

        $total = $this->getQuestionService()->searchQuestionsCount($conditions);

        return $this->getQuestionService()->searchQuestions($conditions, array('createdTime', 'DESC'), 0, $total);
    }

    private function convertQuestionsToItems($testpaper, $questions, $count, $score)
    {
        $items = array();
        for ($i=0; $i<$count; $i++) {
            $question = $questions[$i];
            $items[] = $this->makeItem($testpaper, $question, $score);
            if ($question['subCount'] > 0) {
                $subQuestions = $this->getQuestionService()->findQuestionsByParentId($question['id'], 0, $question['subCount']);
                foreach ($subQuestions as $subQuestion) {
                    $items[] = $this->makeItem($testpaper, $subQuestion, $score);
                }
            }
        }
        return $items;
    }

    private function makeItem($testpaper, $question, $score)
    {
        return array(
            'testId' => $testpaper['id'],
            'questionId' => $question['id'],
            'questionType' => $question['type'],
            'parentId' => $question['parentId'],
            'score' => $score,
            'missScore' => $testpaper['missScore'],
        );
    }

    private function getQuestionService()
    {
        return $this->createService('Question.QuestionService');
    }

}