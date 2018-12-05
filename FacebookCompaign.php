<?php

namespace common\models\applications\facebook;

use common\models\applications\facebook\traits\SearchTrait;
use Yii;
use yii\data\ActiveDataProvider;
use yii\db\ActiveQuery;

/**
 * This is the model class for table "facebook_compaign".
 *
 * @property string $facebook_compaign_id
 * @property string $compaign_name
 * @property string $facebook_page_posts_id
 * @property integer $activated
 *
 * @property FacebookPagePosts $facebookPagePosts
 */
class FacebookCompaign extends \yii\db\ActiveRecord
{
    /**
     * Trait SearchTrait adds search function
     */
    use SearchTrait;
    /**
     * Local Variable just for Validations
     * @var $page
     */
    public $page;

    /**
     * @inheritdoc
     */
    public static function tableName():string
    {
        return 'facebook_compaign';
    }

    /**
     * @inheritdoc
     */
    public function rules():array
    {
        return [
            ['activated', 'default', 'value' => 1],
            [['compaign_name', 'facebook_page_posts_id', 'activated'], 'required'],
            [['compaign_name'], 'string'],
            ['facebook_page_posts_id', 'unique'],
            [['facebook_page_posts_id', 'activated'], 'integer'],
            [['facebook_page_posts_id'], 'exist', 'skipOnError' => true, 'targetClass' => FacebookPagePosts::className(), 'targetAttribute' => ['facebook_page_posts_id' => 'facebook_page_posts_id']],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels():array
    {
        return [
            'facebook_compaign_id' => 'Facebook Compaign ID',
            'compaign_name' => 'Compaign Name',
            'facebook_page_posts_id' => 'Facebook Page Posts ID',
            'activated' => 'Activated',
        ];
    }

    /**
     * @param $id
     * @return mixed
     */
    public static function Edit($id)
    {
        $compaign = FacebookCompaign::findOne(['facebook_compaign_id' => $id]);
        if (empty($compaign)) {
            Yii::$app->session->setFlash('error', Yii::t('app','Compaign not found'));
            return false;
        }
        $compaign_attrs['facebook_page_posts_id'] = $compaign->facebook_page_posts_id;
        $compaign_attrs['page'] = $compaign->facebookPagePosts->facebook_page_id;
        $compaign_attrs['edit_page'] = $compaign_attrs['page'];
        $compaign_attrs['compaign_name'] = $compaign->compaign_name;
        $compaign_attrs['edit'] = $id;

        Yii::$app->session->set('fb_compaign_create', $compaign_attrs);
        return true;
    }

    /**
     * @param $params
     * @return bool
     */
    public static function CreateComplete($params)
    {
        if (isset($params['edit'])) {
            $model = FacebookCompaign::findOne(['facebook_compaign_id' => $params['edit']]);
        } else {
            $model = new FacebookCompaign();
        }

        $model->setAttributes($params);
        return $model->save();
    }

    /**
     * @param $id
     * @param $status
     * @return bool
     */
    public static function ChangeState($id, $status)
    {
        $compaign = static::findOne(['facebook_compaign_id' => $id]);
        if (empty($compaign)) {
            Yii::$app->session->setFlash('eroor', Yii::t('app','Compaign not found'));
            return false;
        }
        $compaign->activated = $status;
        return $compaign->save();
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getFacebookPagePosts():ActiveQuery
    {
        return $this->hasOne(FacebookPagePosts::className(), ['facebook_page_posts_id' => 'facebook_page_posts_id']);
    }
}
