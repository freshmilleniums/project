<?php

namespace common\models;

use Yii;
use yii\db\ActiveRecord;
use yii\helpers\HtmlPurifier;

/**
 * This is the model class for table "settings".
 *
 * @property int $id
 * @property string|null $contract_text
 */
class Settings extends ActiveRecord
{
    const SCENARIO_CONTRACT_TEXT = 'contract_text';

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%settings}}';
    }



    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['contract_text'], 'string'],
            [['contract_text'], 'required', 'on' => self::SCENARIO_CONTRACT_TEXT],
            // Add filter for HTML cleaning
            [['contract_text'], 'filter', 'filter' => [$this, 'purifyHtml']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'contract_text' => 'Contract Text',
        ];
    }

    /**
     * Clean HTML content through HtmlPurifier
     * @param string $value
     * @return string
     */
    public function purifyHtml($value)
    {
        if (empty($value)) {
            return $value;
        }

        // HtmlPurifier configuration for contracts
        $config = [
            // Разрешенные HTML элементы с их атрибутами
            'HTML.Allowed' => 'p[style],br,strong,b,em,i,u,h1[style],h2[style],h3[style],h4[style],h5[style],h6[style],ul,ol,li,table[border|style|class],thead,tbody,tr,th[colspan|rowspan|style|class],td[colspan|rowspan|style|class],a[href],img[src|alt|width|height],div[class|style],span[class|style],blockquote[style]',

            // Разрешенные CSS свойства (расширенный список)
            'CSS.AllowedProperties' => 'color,background-color,font-size,font-weight,text-align,margin,padding,border,border-collapse,width,height,line-height,font-family',

            // Отключаем автоматическое форматирование
            'AutoFormat.RemoveEmpty' => false,
            'AutoFormat.AutoParagraph' => false,
            'AutoFormat.Linkify' => false,
            'AutoFormat.RemoveSpansWithoutAttributes' => false,

            // Настройки обработки HTML
            'HTML.TidyLevel' => 'none',
            'HTML.Doctype' => 'HTML 4.01 Transitional',

            // Сохраняем пробелы и переносы
            'Output.TidyFormat' => false,
            'Core.RemoveInvalidImg' => false,

            // Настройки таблиц
            'HTML.AllowedAttributes' => 'href,src,alt,width,height,class,style,border,colspan,rowspan',

            // Разрешаем все безопасные CSS свойства для выравнивания
            'CSS.Proprietary' => true,
            'CSS.AllowTricky' => true,
        ];

        return HtmlPurifier::process($value, $config);
    }

    /**
     * Get settings record (creates if not exists)
     * @return Settings
     */
    public static function getSettings()
    {
        $settings = static::findOne(1);
        if (!$settings) {
            $settings = new static();
            $settings->id = 1;
            $settings->save(false);
        }
        return $settings;
    }

    /**
     * Get contract text setting value
     * @return string|null
     */
    public static function getContractText()
    {
        return static::getSettings()->contract_text;
    }
}