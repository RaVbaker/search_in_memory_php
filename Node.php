<?php

//Copyright Christopher Thomas 2003 (cst at andrew dot cmu dot edu)
//You may use and redistribute freely as long as you keep this copyright
//notice. Feel free to modify as you want
class Node {
    
    public $left;
    public $right;
    public $data = array();
    public $index;
    
    function Node() {
        $this->left = null;
        $this->right = null;
    }
    
    function search($key) {
        if ($this->index == $key){
            return $this;
        }
        if ($this->left != null && $this->index < $key){
            return $this->left->search($key);
        }
        if ($this->right != null && $this->index > $key){
            return $this->right->search($key);
        }
        return false;
    }
    
    function addItem($key, $data = array()) {
        if ($this->index == $key) {
            $this->data[] = $data;
            return true;
        }
        
        if ($this->left != null && $key < $this->index){
            return $this->left->addItem($key, $data);
        }
        if ($this->right != null && $key > $this->index){
            return $this->right->addItem($key, $data);
        }
        
        if ($this->left == null && $key < $this->index) {
            $this->left = new Node();
            $this->left->index = $key;
            $this->left->data[] = $data;
            return true;
        }
        
        if ($this->right == null && $key > $this->index) {
            $this->right = new Node();
            $this->right->index = $key;
            $this->right->data[] = $data;
            return true;
        }
    }
}