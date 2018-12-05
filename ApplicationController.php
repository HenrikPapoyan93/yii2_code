<?php
namespace common\components;

use yii\web\Controller;

/**
 * Class ApplicationController
 * @package common\components
 */
class ApplicationController extends Controller
{
    /**
     * @var string All Controllers main view paths wich extends from ApplicationController
     */
    private $main_path = "//applications";
    /**
     * @var string Controllers sub directory
     * example`
     * $controller_view_path = "/facebook";
     *
     * this controller's views must be in /applications/facebook/ folder
     *
     * $controller_view_path = "/instagramm";
     *
     * this controller's views must be in /applications/instagramm/ folder
     */
    protected $controller_view_path = "";

    /**
     * ApplicationController overrides function render for setting the view path
     * @Override render()
     * @param string $view
     * @param array $params
     * @return string
     */
    public function render($view, $params = [])
    {
        return parent::render($this->main_path . "/" . $this->controller_view_path . "/" . $view, $params);
    }
}