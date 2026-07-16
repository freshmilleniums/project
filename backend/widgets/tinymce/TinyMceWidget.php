<?php
namespace backend\widgets\tinymce;

use Yii;
use yii\helpers\Html;
use yii\helpers\Json;
use yii\helpers\Url;
use yii\widgets\InputWidget;

class TinyMceWidget extends InputWidget
{
    /**
     * @var array TinyMCE configuration options
     */
    public $clientOptions = [];

    /**
     * @var string Editor theme
     */
    public $theme = 'default';

    /**
     * @var bool Enable image upload functionality
     */
    public $enableImageUpload = true;

    /**
     * @var string URL for image upload endpoint
     */
    public $imageUploadUrl = null;

    /**
     * @var string Interface language
     */
    public $language = 'en';

    /**
     * @var array Contract templates
     */
    public $contractTemplates = [
        'courier_first_name_block' => '{{courier_first_name}}',
        'courier_last_name_block' => '{{courier_last_name}}',
        'courier_address_block' => '{{courier_address}}',
        'company_name_block' => '{{company_name}}',
        'company_address_block' => '{{company_address}}',
        'current_date_block' => '{{сurrent_date}}',
    ];


    /**
     * @var array Predefined configuration presets
     */
    public $presets = [
        'basic' => [
            'height' => 300,
            'plugins' => 'lists link ',
            'toolbar' => 'undo redo | bold italic | bullist numlist | link '
        ],
        'standard' => [
            'height' => 400,
            'plugins' => 'lists link table fullscreen',
            'toolbar' => 'undo redo | bold italic | bullist numlist | link table | fullscreen'
        ],
        'advanced' => [
            'height' => 500,
            'plugins' => 'advlist autolink lists link charmap anchor searchreplace visualblocks code fullscreen insertdatetime media table preview help wordcount',
            'toolbar' => 'undo redo | blocks | bold italic forecolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | removeformat | table | image media | help'
        ],
        'contract' => [
            'height' => 600,
            'plugins' => 'advlist autolink lists link charmap anchor searchreplace visualblocks  fullscreen insertdatetime  table preview help wordcount ',
            'toolbar' => 'undo redo | blocks | bold italic underline | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | table | contracttemplates |  fullscreen  ',
            'menubar' => 'false',
            /*'menu' => [
                'insert' => ['title' => 'Insert', 'items' => 'image media | table | hr']
            ],*/
            'block_formats' => 'Paragraph=p;Heading 1=h1;Heading 2=h2;Heading 3=h3;Preformatted=pre',
            'table_default_attributes' => ['border' => '1'],
            'table_default_styles' => [
                'border-collapse' => 'collapse',
                'width' => '100%'
            ],
            'table_use_colgroups' => true,
            'table_header_type' => 'sectionCells'
        ]
    ];

    /**
     * @var string Selected configuration preset
     */
    public $preset = 'standard';

    public function init()
    {
        parent::init();

        /*if ($this->imageUploadUrl === null) {
            $this->imageUploadUrl = Url::to(['site/tinymce-upload']);
        }*/

        // Apply selected preset
        if (isset($this->presets[$this->preset])) {
            $this->clientOptions = array_merge($this->presets[$this->preset], $this->clientOptions);
        }

        Yii::$app->view->registerCss('.tox-promotion { display: none !important; }');
    }

    public function run()
    {
        TinyMceAsset::register($this->view);

        if ($this->hasModel()) {
            $input = Html::activeTextarea($this->model, $this->attribute, $this->options);
        } else {
            $input = Html::textarea($this->name, $this->value, $this->options);
        }

        $this->registerClientScript();

        return $input;
    }

    protected function registerClientScript()
    {
        $id = $this->options['id'];

        // Default configuration options
        $defaultOptions = [
            'selector' => '#' . $id,
            'menubar' => false,
            'content_style' => 'body { font-family:Helvetica,Arial,sans-serif; font-size:14px }',
            'language' => $this->language,
            'invalid_elements' => 'script,object,embed,iframe',
            'extended_valid_elements' => 'img[src|alt|width|height],a[href|target],table[border|style|class],th[colspan|rowspan|style|class],td[colspan|rowspan|style|class]',
            'verify_html' => false,
            'cleanup' => false,
            'convert_urls' => false,
            'remove_script_host' => false,
            'relative_urls' => false,
            'license_key' => 'gpl',
        ];

        // Image upload configuration
        if ($this->enableImageUpload) {
            $defaultOptions['images_upload_url'] = $this->imageUploadUrl;
            $defaultOptions['images_upload_handler'] = new \yii\web\JsExpression($this->getImageUploadHandler());
        }

        // Merge with custom options (preset + clientOptions)
        $options = array_merge($defaultOptions, $this->clientOptions);

        // Setup configuration
        if ($this->preset === 'contract') {
            $options['setup'] = new \yii\web\JsExpression($this->getContractSetup());
        }

        $js = 'tinymce.init(' . Json::encode($options) . ');';

        $hidePromotionJs = <<<JS
            setTimeout(function() {
                document.querySelectorAll('.tox-promotion').forEach(function(el) {
                    el.style.display = 'none';
                });
            }, 1000);
        JS;

        $this->view->registerJs($js);
        $this->view->registerJs($hidePromotionJs);
    }

    protected function getContractSetup()
    {
        $templates = Json::encode($this->contractTemplates);

        return "
            function(editor) {
                editor.ui.registry.addMenuButton('contracttemplates', {
                    text: 'Templates',
                    tooltip: 'Insert contract template',
                    fetch: function(callback) {
                        var templates = $templates;
                        var items = [];
    
                        if (templates.courier_first_name_block) {
                            items.push({
                                type: 'menuitem',
                                text: 'Insert Courier First Name',
                                onAction: function() {
                                    editor.insertContent(templates.courier_first_name_block);
                                }
                            });
                        }
    
                        if (templates.courier_last_name_block) {
                            items.push({
                                type: 'menuitem',
                                text: 'Insert Courier Last Name',
                                onAction: function() {
                                    editor.insertContent(templates.courier_last_name_block);
                                }
                            });
                        }

                        if (templates.courier_address_block) {
                            items.push({
                                type: 'menuitem',
                                text: 'Insert Courier Address',
                                onAction: function() {
                                    editor.insertContent(templates.courier_address_block);
                                }
                            });
                        }

                        if (templates.company_name_block) {
                            items.push({
                                type: 'menuitem',
                                text: 'Insert Company Name',
                                onAction: function() {
                                    editor.insertContent(templates.company_name_block);
                                }
                            });
                        }

                        if (templates.company_address_block) {
                            items.push({
                                type: 'menuitem',
                                text: 'Insert Company Address',
                                onAction: function() {
                                    editor.insertContent(templates.company_address_block);
                                }
                            });
                        }

                        if (templates.current_date_block) {
                            items.push({
                                type: 'menuitem',
                                text: 'Insert Current Date',
                                onAction: function() {
                                    editor.insertContent(templates.current_date_block);
                                }
                            });
                        }
    
                        callback(items);
                    }
                });
            }
        ";
    }


    protected function getImageUploadHandler()
    {
        $csrfParam = Yii::$app->request->csrfParam;
        $csrfToken = Yii::$app->request->csrfToken;

        return "
        function (blobInfo, success, failure) {
            var xhr, formData;
            xhr = new XMLHttpRequest();
            xhr.withCredentials = false;
            xhr.open('POST', '{$this->imageUploadUrl}');
            xhr.onload = function() {
                var json;
                if (xhr.status !== 200) {
                    failure('HTTP Error: ' + xhr.status);
                    return;
                }
                json = JSON.parse(xhr.responseText);
                if (!json || typeof json.location != 'string') {
                    failure('Invalid JSON: ' + xhr.responseText);
                    return;
                }
                success(json.location);
            };
            formData = new FormData();
            formData.append('file', blobInfo.blob(), blobInfo.filename());
            formData.append('{$csrfParam}', '{$csrfToken}');
            xhr.send(formData);
        }
        ";
    }

}