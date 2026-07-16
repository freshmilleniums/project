<?php

namespace common\models;

use Yii;
use yii\db\ActiveRecord;
use yii\behaviors\TimestampBehavior;

/**
 * This is the model class for table "investors".
 *
 * @property int $id
 * @property string $first_name
 * @property string $last_name
 * @property string $email
 * @property string|null $address
 * @property float|null $net_value
 * @property int|null $investor_type
 * @property string|null $comment
 * @property int|null $created_by
 * @property int $created_at
 * @property int $updated_at
 * @property int|null $employees_count
 *
 * @property User $creator
 * @property InvestorEmployee[] $investorEmployees
 * @property User[] $employees
 */
class Investor extends ActiveRecord
{
    const TYPE_ANGEL = 1;
    const TYPE_VC = 2;
    const TYPE_PRIVATE_EQUITY = 3;
    const TYPE_CORPORATE = 4;
    const TYPE_OTHER = 5;

    public $employees_count;

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'investors';
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
            [['first_name', 'last_name', 'email'], 'required'],
            [['net_value'], 'number'],
            [['investor_type', 'created_by', 'created_at', 'updated_at', 'employees_count'], 'integer'],
            [['first_name', 'last_name', 'email'], 'string', 'max' => 255],
            [['address'], 'string', 'max' => 500],
            [['comment'], 'string', 'max' => 2000],
            [['email'], 'email'],
            [['investor_type'], 'in', 'range' => [self::TYPE_ANGEL, self::TYPE_VC, self::TYPE_PRIVATE_EQUITY, self::TYPE_CORPORATE, self::TYPE_OTHER]],
            [['created_by'], 'exist', 'skipOnError' => true, 'targetClass' => User::class, 'targetAttribute' => ['created_by' => 'id']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'first_name' => 'First Name',
            'last_name' => 'Last Name',
            'email' => 'Email',
            'address' => 'Address',
            'net_value' => 'Net Value',
            'investor_type' => 'Investor Type',
            'comment' => 'Comment',
            'created_by' => 'Created By',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
        ];
    }

    /**
     * Gets query for [[Creator]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getCreator()
    {
        return $this->hasOne(User::class, ['id' => 'created_by']);
    }

    /**
     * Gets query for [[InvestorEmployees]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getInvestorEmployees()
    {
        return $this->hasMany(InvestorEmployee::class, ['investor_id' => 'id']);
    }

    /**
     * Gets query for [[Employees]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getEmployees()
    {
        return $this->hasMany(User::class, ['id' => 'employee_id'])
            ->viaTable('investor_employee', ['investor_id' => 'id']);
    }

    /**
     * Get investor type list
     * @return array
     */
    public static function getTypeList()
    {
        return [
            self::TYPE_ANGEL => 'Angel Investor',
            self::TYPE_VC => 'Venture Capital',
            self::TYPE_PRIVATE_EQUITY => 'Private Equity',
            self::TYPE_CORPORATE => 'Corporate Investor',
            self::TYPE_OTHER => 'Other',
        ];
    }

    /**
     * Get investor type name
     * @return string
     */
    public function getTypeName()
    {
        $types = self::getTypeList();
        return isset($types[$this->investor_type]) ? $types[$this->investor_type] : 'Unknown';
    }

    /**
     * Get full name
     * @return string
     */
    public function getFullName()
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    public static function getInvestorsDropdownList(): array
    {
        $investors = self::find()
            ->select(["CONCAT(first_name, ' ', last_name) AS full_name", 'id'])
            ->orderBy('first_name ASC')
            ->asArray()
            ->all();

        return \yii\helpers\ArrayHelper::map($investors, 'id', 'full_name');
    }
}