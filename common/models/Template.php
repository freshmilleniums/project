<?php

namespace common\models;

use Yii;
use yii\behaviors\TimestampBehavior;

/**
 * This is the model class for table "templates".
 *
 * @property int $id
 * @property int $category
 * @property string $title
 * @property string|null $subject
 * @property string $body
 * @property int|null $created_by
 * @property int $created_at
 * @property int $updated_at
 *
 * @property User $creator
 * @property TemplatesDocuments[] $documents
 */
class Template extends \yii\db\ActiveRecord
{
    const CATEGORY_SYSTEM = 1;
    const CATEGORY_REMINDERS = 2;
    const CATEGORY_BUSINESS = 3;
    const CATEGORY_CHAT = 4;
    const CATEGORY_INVESTOR = 5;
    const CATEGORY_PROJECT = 6;

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'templates';
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
            [['category', 'title', 'body'], 'required'],
            [['category', 'created_by', 'created_at', 'updated_at'], 'integer'],
            [['body'], 'string'],
            [['title'], 'string', 'max' => 255],
            [['subject'], 'string', 'max' => 500],
            [['category'], 'in', 'range' => array_keys(self::getCategoryList())],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'category' => 'Category',
            'title' => 'Title',
            'subject' => 'Subject',
            'body' => 'Body',
            'created_by' => 'Created By',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
        ];
    }

    /**
     * Get category list for dropdowns
     * @return array
     */
    public static function getCategoryList()
    {
        return [
            self::CATEGORY_SYSTEM => 'System',
            self::CATEGORY_REMINDERS => 'Reminders',
            self::CATEGORY_BUSINESS => 'Business',
            self::CATEGORY_CHAT => 'Chat',
            self::CATEGORY_INVESTOR => 'Investor',
            self::CATEGORY_PROJECT => 'Project',
        ];
    }

    /**
     * Get category name
     * @return string
     */
    public function getCategoryName()
    {
        $categories = self::getCategoryList();
        return isset($categories[$this->category]) ? $categories[$this->category] : 'Unknown';
    }

    /**
     * Get available macros list
     * @return array [macro => description]
     */
    public static function getAvailableMacros()
    {
        return [
            '{{FullName}}' => 'Employee full name',
            '{{FirstName}}' => 'Employee first name',
            '{{LastName}}' => 'Employee last name',
            '{{Email}}' => 'Employee email',
            '{{Position}}' => 'Position title',
            '{{Project}}' => 'Current project name',
            '{{ProjectType}}' => 'Project type',
            '{{Administrator}}' => 'Administrator full name',
            '{{PhoneNumber}}' => 'Employee phone number',
            '{{Address}}' => 'Employee address',
            '{{City}}' => 'Employee city',
            '{{State}}' => 'Employee state',
            '{{ZipCode}}' => 'Employee zip code',
            '{{Country}}' => 'Employee country',
        ];
    }

    /**
     * Replace macros with actual user data
     * @param int $userId
     * @return array ['subject' => '...', 'body' => '...']
     */
    public function replaceMacros($userId)
    {
        $user = User::findOne($userId);
        if (!$user) {
            return [
                'subject' => $this->subject,
                'body' => $this->body,
            ];
        }

        $body = $this->body;
        $subject = $this->subject;

        $replacements = [
            '{{FullName}}' => trim($user->first_name . ' ' . $user->last_name),
            '{{FirstName}}' => $user->first_name,
            '{{LastName}}' => $user->last_name,
            '{{Email}}' => $user->email,
            '{{Position}}' => $user->position_title ?? '',
            '{{PhoneNumber}}' => $user->phone_number ?? '',
            '{{Address}}' => $user->address ?? '',
            '{{City}}' => $user->city ?? '',
            '{{State}}' => $user->state ?? '',
            '{{ZipCode}}' => $user->zip_code ?? '',
            '{{Country}}' => $user->country ?? '',
        ];

        if ($user->current_project_id) {
            $project = Project::findOne($user->current_project_id);
            if ($project) {
                $replacements['{{Project}}'] = $project->name;
                $replacements['{{ProjectType}}'] = $project->getTypeName();
            }
        }

        if ($user->administrator_id) {
            $admin = User::findOne($user->administrator_id);
            if ($admin) {
                $replacements['{{Administrator}}'] = trim($admin->first_name . ' ' . $admin->last_name);
            }
        }

        foreach ($replacements as $macro => $value) {
            $body = str_replace($macro, $value, $body);
            $subject = str_replace($macro, $value, $subject);
        }

        return [
            'subject' => $subject,
            'body' => $body,
        ];
    }

    /**
     * Get creator relation
     * @return \yii\db\ActiveQuery
     */
    public function getCreator()
    {
        return $this->hasOne(User::class, ['id' => 'created_by']);
    }

    /**
     * Get creator name
     * @return string|null
     */
    public function getCreatorName()
    {
        if ($this->created_by && $this->creator) {
            return trim($this->creator->first_name . ' ' . $this->creator->last_name);
        }
        return null;
    }

    /**
     * Get documents relation
     * @return \yii\db\ActiveQuery
     */
    public function getDocuments()
    {
        return $this->hasMany(TemplatesDocuments::class, ['template_id' => 'id']);
    }
}