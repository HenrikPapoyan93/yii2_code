<?php
namespace frontend\controllers;

use Yii;
use common\models\applications\facebook\{
    FacebookCompaign,
    FacebookPagePosts,
    FacebookPagePostsKeywords,
    FacebookUser,
    FacebookPage
};
use frontend\rules\FacebookCreateRule;
use common\components\ApplicationController;
use yii\filters\AccessControl;
use yii\filters\AccessRule;
use yii\helpers\Url;

/**
 * Class FacebookController
 * @package frontend\controllers
 */
class FacebookController extends ApplicationController
{

    /**
     * @var string view files directory
     */
    protected $controller_view_path = "facebook";

    /**
     * @var array all actions for creating compaign
     */
    private $create_actions = [
        "create",
        "select-page",
        "posts",
        "add-keyword",
        "create-complete",
        "remove-keyword"
    ];

    /**
     * @inheritdoc
     */
    public function behaviors():array
    {
        return [
            'access' => [
                'class' => AccessControl::className(),
                'denyCallback' => function ($rule, $action) {
                    Yii::$app->session->setFlash("error", Yii::t("app","Please connect to your Facebook account to continue"));
                    return $this->redirect(["index"]);
                },
                'rules' => [
                    [
                        'actions' => ['test'],
                        'allow' => true,
                        'roles' => ['?']
                    ],
                    [
                        'actions' => ['index', 'facebook-connect', 'facebook-submit-user', 'remove-keyword', 'edit', 'on-off'],
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                    [
                        'class' => AccessRule::className(),
                        'actions' => ['all', 'create'],
                        'allow' => true,
                        'roles' => ['@'],
                        'matchCallback' => function ($rule, $action):bool {
                            return !empty(Yii::$app->Facebook->getAccessToken());
                        }
                    ],
                    [
                        'actions' => ['facebook-connect'],
                        'allow' => true,
                        'roles' => ['@'],
                        'matchCallback' => function ($rule, $action):bool {
                            $get = Yii::$app->request->get();
                            return isset($get["code"]) && isset($get["state"]);
                        }
                    ],
                    [
                        'class' => FacebookCreateRule::className(),
                        'actions' => ['select-page', 'posts', 'add-keyword', 'create-complete'],
                        'allow' => true,
                        'roles' => ['@'],
                        'param_name' => 'fb_compaign_create',
                        'param_type' => 'SESSION',
                        'params' => [
                            'select-page' => 'compaign_name',
                            'posts' => 'page',
                            'add-keyword' => 'page',
                            'create-complete' => 'compaign_name|page|facebook_page_posts_id'
                        ],
                        'denyCallbackRule' => function ($rule, $action) {
                            $session_params = Yii::$app->session->get('fb_compaign_create');
                            if (isset($session_params['edit']) && $session_params['edid_page'] != $session_params['page']) {
                                Yii::$app->session->setFlash("error", Yii::t("app","You have changed Fun page now you must select post"));
                                return $this->redirect(Yii::$app->request->referrer);
                            }
                            Yii::$app->session->setFlash("error", Yii::t("app","You must create compaign to access this action"));
                            return $this->redirect(["facebook/all"]);
                        }
                    ]
                ],
            ]
        ];
    }

    /**
     * @inheritdoc
     */
    public function actions():array
    {
        return [
            'error' => [
                'class' => 'yii\web\ErrorAction',
            ],
            'captcha' => [
                'class' => 'yii\captcha\CaptchaAction',
                'fixedVerifyCode' => YII_ENV_TEST ? 'testme' : null,
            ],
        ];
    }

    /**
     * @param \yii\base\Action $action
     * @return bool
     * @throws \yii\web\BadRequestHttpException
     */
    public function beforeAction($action) :bool
    {
        if (!in_array($action->id, $this->create_actions)) {
            Yii::$app->session->remove("fb_compaign_create");
        }
        return parent::beforeAction($action);
    }

    /**
     * If user is not registered in Facebook he will be redirected here for Login or Register with FB
     * @return string page view
     */
    public function actionIndex()
    {
        $fb = Yii::$app->Facebook;
        $fb_url = $fb
            ->getRedirectLoginHelper()
            ->getLoginUrl(Url::to(['facebook-connect'], true), [FacebookUser::getScopesAsString()]);

        if ($fb->getAccessToken()) {
            Yii::$app->session->setFlash('error', Yii::t('app','You have already connected'));
            return $this->redirect(['facebook/all']);
        }

        return $this->render('index', [
            'fb_url' => $fb_url
        ]);
    }

    /**
     * Connecting to Facebook account
     * @return string|\yii\web\Response
     */
    public function actionFacebookConnect()
    {
        $code = Yii::$app->request->get()['code'] ?? '';
        $fb = Yii::$app->Facebook;

        if ($fb->getAccessToken()) {
            Yii::$app->session->setFlash('error', Yii::t('app','You have already connected'));
            return $this->redirect(['facebook/all']);
        }

        if ($fb->validateConnection($code)) {
            $fb_user_data = FacebookUser::getUserData();
            return $this->render('connect', [
                'fb_user_data' => $fb_user_data
            ]);
        } else {
            Yii::$app->session->setFlash('error', Yii::t('app','Try connect again'));
            return $this->redirect('index');
        }
    }

    /**
     * Submit Facebook account as main
     */
    public function actionFacebookSubmitUser()
    {
        $request = Yii::$app->request->get();

        $model = FacebookUser::submitUser($request);
        if (!empty($model)) {
            FacebookPage::getUserPagesFromFacebook($model->facebook_user_id);
            FacebookPagePosts::getPagePostsFromFacebook(FacebookPage::$models);
            return $this->redirect(['facebook/all']);
        } else {
            Yii::$app->session->setFlash('error', Yii::t('app','Something went wrong please try again'));
            return $this->redirect(['facebook/index']);
        }
    }

    /**
     * Show all Compaigns
     * @return string page view
     */
    public function actionAll()
    {
        $searchModel = new FacebookCompaign();
        $compaigns = $searchModel->search(Yii::$app->request->get());

        return $this->render('all', [
            'compaigns' => $compaigns,
            'searchModel' => $searchModel,
        ]);
    }

    /**
     * Edit existing Facebook compaign
     * @param $id
     * @return \yii\web\Response
     */
    public function actionEdit($id)
    {
        if (!FacebookCompaign::Edit($id)) {
            return $this->redirect(['facebook/all']);
        }

        return $this->redirect(['facebook/create']);
    }

    /**
     * Create new Compaign
     * @return string page view
     */
    public function actionCreate()
    {
        $post = Yii::$app->request->post();
        $compaign_attrs = Yii::$app->session->get('fb_compaign_create');
        $model = new FacebookCompaign();

        if (isset($compaign_attrs['compaign_name'])) {
            $model->compaign_name = $compaign_attrs['compaign_name'];
        }
        if (isset($post['FacebookCompaign']['compaign_name'])) {
            $compaign_attrs['compaign_name'] = $post['FacebookCompaign']['compaign_name'];
            Yii::$app->session->set('fb_compaign_create', $compaign_attrs);
            return $this->redirect(['facebook/select-page']);
        }
        return $this->render('create', [
            'model' => $model
        ]);
    }

    /**
     * Select Facebook page for compaign
     * @return \yii\web\Response
     */
    public function actionSelectPage()
    {
        $post = Yii::$app->request->post();
        $compaign_attrs = Yii::$app->session->get('fb_compaign_create');
        $model = new FacebookCompaign();

        if (isset($compaign_attrs['page'])) {
            $model->page = $compaign_attrs['page'];
        }
        if (isset($post['FacebookCompaign']['page']) && !empty($post['FacebookCompaign']['page'])) {

            $compaign_attrs['page'] = $post['FacebookCompaign']['page'];
            if (isset($compaign_attrs['edit']) && $compaign_attrs['edit_page'] != $compaign_attrs['page']) {
                unset($compaign_attrs['facebook_page_posts_id']);
            }
            Yii::$app->session->set('fb_compaign_create', $compaign_attrs);

            return $this->redirect(['facebook/posts', 'page_id' => $compaign_attrs['page']]);
        } else {
            $pages = FacebookPage::find()->all();
        }
        return $this->render('select-page', [
            'model' => $model,
            'pages' => $pages
        ]);
    }

    /**
     * Facebook Fun page's posts
     * @return string
     */
    public function actionPosts()
    {
        $page_id = Yii::$app->request->get('page_id') ?? Yii::$app->session->get('fb_compaign_create')['page'] ?? '';

        $dataProvider = FacebookPagePosts::getPagePostsProvider($page_id);
        return $this->render('posts', [
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Add keyword on post
     * @param $facebook_page_posts_id
     * @return string|\yii\web\Response
     */
    public function actionAddKeyword($facebook_page_posts_id)
    {
        $post = Yii::$app->request->post();
        $model = new FacebookPagePostsKeywords();

        $compaign_attrs = Yii::$app->session->get('fb_compaign_create');
        $compaign_attrs['facebook_page_posts_id'] = $facebook_page_posts_id;
        Yii::$app->session->set('fb_compaign_create', $compaign_attrs);

        if (empty($post)) {
            $dataProvider = $model->getPostKeywordsProvider($facebook_page_posts_id);
            return $this->render('add-keyword', [
                'dataProvider' => $dataProvider,
                'model' => $model,
                'facebook_page_posts_id' => $facebook_page_posts_id
            ]);
        }

        $model->load($post);

        if ($model->save()) {
            Yii::$app->session->setFlash('success', Yii::t('app','New Keyword added successfully'));
        } else {
            Yii::$app->session->setFlash('error', Yii::t('app','Something went wrong'));
        }
        return $this->redirect(['facebook/add-keyword', 'facebook_page_posts_id' => $facebook_page_posts_id]);
    }

    /**
     * Creating FacebookCompaign model,validating and saving
     * @return string|\yii\web\Response
     */
    public function actionCreateComplete()
    {
        $params = Yii::$app->session->get('fb_compaign_create');

        if(FacebookCompaign::CreateComplete($params)){
            $title = isset($params['edit']) ? 'Successfully Edited Campaign' : 'Successfully Created Campaign';
            $description = isset($params['edit'])
                ? 'If you are seeing this page, it means you have successfully edited your campaign. Click continue to return back to the Dashboard.'
                : 'If you are seeing this page, it means you have successfully created and published your campaign. Click continue to return back to the Dashboard.';

            Yii::$app->session->remove('fb_compaign_create');

            return $this->render('create-complete', [
                'title' => Yii::t('app',$title),
                'description' => Yii::t('app',$description),
            ]);
        } else {

            return $this->redirect(Yii::$app->request->referrer);
        }
    }

    /**
     * Remove keyword from post
     * @param $facebook_page_posts_keywords_id
     * @param $facebook_page_posts_id
     * @return string|\yii\web\Response
     */
    public function actionRemoveKeyword($facebook_page_posts_keywords_id, $facebook_page_posts_id)
    {
        $count = FacebookPagePostsKeywords::deleteAll(['facebook_page_posts_keywords_id' => $facebook_page_posts_keywords_id]);
        Yii::$app->session->setFlash($count > 0 ? 'success' : 'error', $count > 0 ? Yii::t('app','Keyword deleted successfully') : Yii::t('app','Keyword not found'));
        return $this->redirect(['facebook/add-keyword', 'facebook_page_posts_id' => $facebook_page_posts_id]);
    }

    /**
     * Change Compaign status activate/disable
     * @param $id
     * @param $status
     * @return \yii\web\Response
     */
    public function actionOnOff($id, $status)
    {
        if(FacebookCompaign::ChangeState($id,$status)){
            Yii::$app->session->setFlash('success', Yii::t('app','Compaign status changes successfully'));
        }
        return $this->redirect(['facebook/all']);
    }

    /**
     * Facebook user's all Pages
     * @return string page view
     */
    public function actionFacebookPages()
    {
        /**
         * TODO get all pages for this fb user
         */
    }

    /**
     * Facebook page's all posts
     * @return string page view
     */
    public function actionFacebookPosts()
    {
        /**
         * TODO get all posts for this fb page
         */
    }
}
