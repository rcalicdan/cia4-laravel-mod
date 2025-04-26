<?php

namespace Rcalicdan\Ci4Larabridge\Blade;

class PaginationRenderer
{
    protected $view;
    protected $viewBridge;
    protected $data = [];

    public function __construct()
    {
        $this->viewBridge = service('blade');
        $this->viewBridge->getBlade()->addNamespace('pagination', APPPATH . 'Views/pagination');
    }

    public function make($view, $data = [])
    {
        $this->view = $view;
        $this->data = $data;

        return $this;
    }

    public function setData($data = [])
    {
        $this->data = $data;

        return $this;
    }

    public function render()
    {
        return $this->viewBridge->setData($this->data)->render($this->view);
    }

    public function __toString()
    {
        return $this->render();
    }
}
