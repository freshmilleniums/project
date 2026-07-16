<?php

namespace common\models;

use Yii;
use yii\db\ActiveRecord;
use yii\behaviors\TimestampBehavior;

/**
 * This is the model class for table "user_comments".
 *
 * @property int $id
 * @property int $user_id
 * @property int $commented_by
 * @property string $comment
 * @property int $created_at
 * @property int $updated_at
 *
 * @property User $user
 * @property User $commentedBy
 */
class UserComments extends ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%user_comments}}';
    }

    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        return [
            TimestampBehavior::class,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['user_id', 'commented_by', 'comment'], 'required'],
            [['user_id', 'commented_by'], 'integer'],
            [['comment'], 'string', 'max' => 1000],
            [['user_id'], 'exist', 'skipOnError' => true, 'targetClass' => User::class, 'targetAttribute' => ['user_id' => 'id']],
            [['commented_by'], 'exist', 'skipOnError' => true, 'targetClass' => User::class, 'targetAttribute' => ['commented_by' => 'id']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'user_id' => 'User ID',
            'commented_by' => 'Commented By',
            'comment' => 'Comment',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
        ];
    }

    /**
     * Gets query for related User (the user being commented on).
     *
     * @return \yii\db\ActiveQuery
     */
    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    /**
     * Gets query for related User (the user who made the comment).
     *
     * @return \yii\db\ActiveQuery
     */
    public function getCommentedBy()
    {
        return $this->hasOne(User::class, ['id' => 'commented_by']);
    }
}