<?php
/**
 * Created by PhpStorm.
 * User: Claude
 * Date: 6/16/2017
 * Time: 3:49 PM
 */
use yii\helpers\{Html,Url};
use yii\grid\GridView;
use common\models\applications\facebook\FacebookPagePostsKeywords;

$this->title = Yii::t('app','View Compaigns');

//$this->params['breadcrumbs'][] = $this->title;
?>
<div class="row">
    <div class="col-md-12">
        <h3 class="text-primary">
            <?=$this->title?>
        </h3>
    </div>
</div>
<div class="row">
    <div class="col-md-12">
        <div class="well">
            <blockquote>
                <p>
                    <?=Yii::t('app','Find below the list of the Campaigns running in the system.');?>
                </p>
            </blockquote>
        </div>
    </div>
</div>
<div class="row">
    <div class="col-md-12">
        <?= GridView::widget([
            'dataProvider' => $compaigns,
            'filterModel' => $searchModel,
            'columns' => [
                ['class' => 'yii\grid\SerialColumn'],
                'compaign_name',
                'facebookPagePosts.facebookPage.page_name',
                [
                    'format'=>'raw',
                    'label'=>'Post',
                    'value'=>function($model){
                        $container = '<div class="well-sm text-justify label-default"><span style="color:white">{message}</span></div>';
                        return str_replace('{message}',$model->facebookPagePosts->message,$container);
                    }
                ],
                [
                    'label'=>'Keywords',
                    'value'=>function($model){
                        $text = '';
                        $keywords = $model->facebookPagePosts->facebookPagePostsKeywords;
                        foreach($keywords as $keyword){
                            $reply_type = FacebookPagePostsKeywords::REPLY_TYPES[$keyword->reply_type];
                            $text .= $keyword->keyword.'('.$keyword->reply.'-'.$reply_type.'),';
                        }
                        return $text;
                    }
                ],
                [
                    'class' => 'yii\grid\ActionColumn',
                    'buttons' => ['edit','on-off'],
                    'template' => '{on-off}{edit}',
                    'buttons' => [
                        'on-off' => function($url,$model,$key){
                            $status = $model->activated;
                            if($status == 0){
                                return Html::a(
                                    '<span class="text-success glyphicon glyphicon-ok"></span>',
                                    $url.'&status=1',
                                    [
                                        'title'=>'Turn On'
                                    ]
                                );
                            }else{
                                return Html::a(
                                    '<span class="text-danger glyphicon glyphicon-remove"></span>',
                                    $url.'&status=0',
                                    [
                                        'title'=>'Turn off'
                                    ]
                                );
                            }
                        },
                        'edit' => function($url,$model,$key){
                            return Html::a(
                                '<span class="text-primary glyphicon glyphicon-pencil"></span>',
                                $url,
                                [
                                    'title'=>'Edit Compaign'
                                ]
                            );
                        }
                    ],

                ],
            ],
        ]) ?>
    </div>
</div>