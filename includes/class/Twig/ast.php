<?php
/**
 * Twig::AST
 * ~~~~~~~~~
 *
 * This module implements the abstract syntax tree and compiler.
 *
 * :copyright: 2008 by Armin Ronacher.
 * :license: BSD.
 */


class Twig_Node
{
    public $lineno;

    public function __construct($lineno)
    {
        $this->lineno = $lineno;
    }

    public function compile($compiler)
    {
    }
}


class Twig_NodeList extends Twig_Node
{
    public $nodes;

    public function __construct($nodes, $lineno)
    {
        parent::__construct($lineno);
        $this->nodes = $nodes;
    }

    public function compile($compiler)
    {
        foreach ($this->nodes as $node)
            $node->compile($compiler);
    }

    public static function fromArray($array, $lineno)
    {
        if (count($array) == 1)
            return $array[0];
        return new Twig_NodeList($array, $lineno);
    }
}


class Twig_Module extends Twig_Node
{
    public $body;
    public $extends;
    public $blocks;
    public $filename;
    public $id;

    public function __construct($body, $extends, $blocks, $filename)
    {
        parent::__construct(1);
        $this->body = $body;
        $this->extends = $extends;
        $this->blocks = $blocks;
        $this->filename = $filename;
    }

    public function compile($compiler)
    {
        $compiler->raw("<?php\n");
        if (!is_null($this->extends)) {
            $compiler->raw('$this->requireTemplate(');
            $compiler->repr($this->extends);
            $compiler->raw(");\n");
        }
        $compiler->raw('class __TwigTemplate_' . md5($this->filename));
        if (!is_null($this->extends)) {
            $parent = md5($this->extends);
            $compiler->raw(" extends __TwigTemplate_$parent {\n");
        }
        else {
            $compiler->raw(" {\npublic function render(\$context) {\n");
            $this->body->compile($compiler);
            $compiler->raw("}\n");
        }

        foreach ($this->blocks as $node)
            $node->compile($compiler);

        $compiler->raw("}\n");
    }
}


class Twig_Print extends Twig_Node
{
    public $expr;

    public function __construct($expr, $lineno)
    {
        parent::__construct($lineno);
        $this->expr = $expr;
    }

    public function compile($compiler)
    {
        $compiler->addDebugInfo($this);
        $compiler->raw('echo ');
        $this->expr->compile($compiler);
        $compiler->raw(";\n");
    }
}


class Twig_Text extends Twig_Node
{
    public $data;

    public function __construct($data, $lineno)
    {
        parent::__construct($lineno);
        $this->data = $data;
    }

    public function compile($compiler)
    {
        $compiler->addDebugInfo($this);
        $compiler->raw('echo ');
        $compiler->string($this->data);
        $compiler->raw(";\n");
    }
}


class Twig_ForLoop extends Twig_Node
{
    public $is_multitarget;
    public $item;
    public $seq;
    public $body;
    public $else;

    public function __construct($is_multitarget, $item, $seq, $body, $else,
                    $lineno)
    {
        parent::__construct($lineno);
        $this->is_multitarget = $is_multitarget;
        $this->item = $item;
        $this->seq = $seq;
        $this->body = $body;
        $this->else = $else;
        $this->lineno = $lineno;
    }

    public function compile($compiler)
    {
        $compiler->addDebugInfo($this);
        $compiler->pushContext();
        $compiler->raw('foreach (twig_iterate($context, ');
        $this->seq->compile($compiler);
        $compiler->raw(") as \$iterator) {\n");
        if ($this->is_multitarget) {
            $compiler->raw('twig_set_loop_context_multitarget($context, ' .
                       '$iterator, array(');
            $idx = 0;
            foreach ($this->item as $node) {
                if ($idx++)
                    $compiler->raw(', ');
                $compiler->repr($node->name);
            }
            $compiler->raw("));\n");
        }
        else {
            $compiler->raw('twig_set_loop_context($context, $iterator, ');
            $compiler->repr($this->item->name);
            $compiler->raw(");\n");
        }
        $this->body->compile($compiler);
        $compiler->raw("}\n");
        if (!is_null($this->else)) {
            $compiler->raw("if (!\$context['loop']['iterated']) {\n");
            $this->else->compile($compiler);
            $compiler->raw('}');
        }
        $compiler->popContext();
    }
}

class Twig_PaginateLoop extends Twig_Node
{
    public $item;
    public $seq;
    public $body;
    public $else;

    public function __construct($item, $per_page, $target,
                    $as, $body, $else, $lineno)
    {
        parent::__construct($lineno);
        $this->item = $item;
        $this->per_page = $per_page;
        $this->seq = $target;
        $this->as = $as;
        $this->body = $body;
        $this->else = $else;
        $this->lineno = $lineno;
    }

    public function compile($compiler)
    {
        $compiler->addDebugInfo($this);
        $compiler->pushContext();
        $compiler->raw('twig_paginate($context,');
        $compiler->raw('"'.$this->as->name.'", ');
        if (isset($this->seq->node) and isset($this->seq->attr)) {
            $compiler->raw('array($context["::parent"]["');
            $compiler->raw($this->seq->node->name.'"],');
            $compiler->raw('"'.$this->seq->attr->value.'")');
        } else
            $this->seq->compile($compiler);
        $compiler->raw(', ');
        $this->per_page->compile($compiler);
        $compiler->raw(");\n");
        $compiler->raw('foreach (twig_iterate($context,');
        $compiler->raw(' $context["::parent"]["'.$this->as->name);
        $compiler->raw("\"]->paginated) as \$iterator) {\n");
        $compiler->raw('twig_set_loop_context($context, $iterator, ');
        $compiler->repr($this->item->name);
        $compiler->raw(");\n");
        $this->body->compile($compiler);
        $compiler->raw("}\n");
        if (!is_null($this->else)) {
            $compiler->raw("if (!\$context['loop']['iterated']) {\n");
            $this->else->compile($compiler);
            $compiler->raw('}');
        }
        $compiler->popContext();
    }
}


class Twig_IfCondition extends Twig_Node
{
    public $tests;
    public $else;

    public function __construct($tests, $else, $lineno)
    {
        parent::__construct($lineno);
        $this->tests = $tests;
        $this->else = $else;
    }

    public function compile($compiler)
    {
        $compiler->addDebugInfo($this);
        $idx = 0;
        foreach ($this->tests as $test) {
            $compiler->raw(($idx++ ? "}\nelse" : '') . 'if (');
            $test[0]->compile($compiler);
            $compiler->raw(") {\n");
            $test[1]->compile($compiler);
        }
        if (!is_null($this->else)) {
            $compiler->raw("} else {\n");
            $this->else->compile($compiler);
        }
        $compiler->raw("}\n");
    }
}


class Twig_Block extends Twig_Node
{
    public $name;
    public $body;
    public $parent;

    public function __construct($name, $body, $lineno, $parent=NULL)
    {
        parent::__construct($lineno);
        $this->name = $name;
        $this->body = $body;
        $this->parent = $parent;
    }

    public function replace($other)
    {
        $this->body = $other->body;
    }

    public function compile($compiler)
    {
        $compiler->addDebugInfo($this);
        $compiler->format('public function block_%s($context) {' . "\n",
                  $this->name);
        if (!is_null($this->parent))
            $compiler->raw('$context[\'::superblock\'] = array($this, ' .
                       "'parent::block_$this->name');\n");
        $this->body->compile($compiler);
        $compiler->format("}\n\n");
    }
}


class Twig_BlockReference extends Twig_Node
{
    public $name;

    public function __construct($name, $lineno)
    {
        parent::__construct($lineno);
        $this->name = $name;
    }

    public function compile($compiler)
    {
        $compiler->addDebugInfo($this);
        $compiler->format('$this->block_%s($context);' . "\n", $this->name);
    }
}


class Twig_Super extends Twig_Node
{
    public $block_name;

    public function __construct($block_name, $lineno)
    {
        parent::__construct($lineno);
        $this->block_name = $block_name;
    }

    public function compile($compiler)
    {
        $compiler->addDebugInfo($this);
        $compiler->raw('parent::block_' . $this->block_name . '($context);' . "\n");
    }
}


class Twig_Include extends Twig_Node
{
    public $expr;

    public function __construct($expr, $lineno)
    {
        parent::__construct($lineno);
        $this->expr = $expr;
    }

    public function compile($compiler)
    {
        $compiler->addDebugInfo($this);
        $compiler->raw('twig_get_current_template()->loader->getTemplate(');
        $this->expr->compile($compiler);
        $compiler->raw(')->display($context);' . "\n");
    }
}


class Twig_URL extends Twig_Node
{
    public $expr;

    public function __construct($expr, $cont, $lineno)
    {
        parent::__construct($lineno);
        $this->expr = $expr;
        $this->cont = $cont;
    }

    public function compile($compiler)
    {
        $compiler->addDebugInfo($this);
        $compiler->raw('echo url(');
        $this->expr->compile($compiler);

        if (!empty($this->cont) and class_exists($this->cont->name."Controller") and is_callable(array($this->cont->name."Controller", "current")))
            $compiler->raw(", ".$this->cont->name."Controller::current()");

        $compiler->raw(');'."\n");
    }
}


class Twig_AdminURL extends Twig_Node
{
    public $expr;

    public function __construct($expr, $lineno)
    {
        parent::__construct($lineno);
        $this->expr = $expr;
    }

    public function compile($compiler)
    {
        $compiler->addDebugInfo($this);
        $compiler->raw('echo fix(Config::current()->chyrp_url."/admin/?action=".(');
        $this->expr->compile($compiler);
        $compiler->raw('));'."\n");
    }
}


class Twig_Expression extends Twig_Node
{

}


class Twig_ConditionalExpression extends Twig_Expression
{
    public $expr1;
    public $expr2;
    public $expr3;

    public function __construct($expr1, $expr2, $expr3, $lineno)
    {
        parent::__construct($lineno);
        $this->expr1 = $expr1;
        $this->expr2 = $expr2;
        $this->expr3 = $expr3;
    }

    public function compile($compiler)
    {
        $compiler->raw('(');
        $this->expr1->compile($compiler);
        $compiler->raw(') ? (');
        $this->expr2->compile($compiler);
        $compiler->raw(') ; (');
        $this->expr3->compile($compiler);
        $compiler->raw(')');
    }
}


class Twig_BinaryExpression extends Twig_Expression
{
    public $left;
    public $right;

    public function __construct($left, $right, $lineno)
    {
        parent::__construct($lineno);
        $this->left = $left;
        $this->right = $right;
    }

    public function compile($compiler)
    {
        $compiler->raw('(');
        $this->left->compile($compiler);
        $compiler->raw(') ');
        $this->operator($compiler);
        $compiler->raw(' (');
        $this->right->compile($compiler);
        $compiler->raw(')');
    }
}


class Twig_OrExpression extends Twig_BinaryExpression
{
    public function operator($compiler)
    {
        return $compiler->raw('||');
    }
}


class Twig_AndExpression extends Twig_BinaryExpression
{
    public function operator($compiler)
    {
        return $compiler->raw('&&');
    }
}


class Twig_AddExpression extends Twig_BinaryExpression
{
    public function operator($compiler)
    {
        return $compiler->raw('+');
    }
}


class Twig_SubExpression extends Twig_BinaryExpression
{
    public function operator($compiler)
    {
        return $compiler->raw('-');
    }
}


class Twig_ConcatExpression extends Twig_BinaryExpression
{
    public function operator($compiler)
    {
        return $compiler->raw('.');
    }
}


class Twig_MulExpression extends Twig_BinaryExpression
{
    public function operator($compiler)
    {
        return $compiler->raw('*');
    }
}


class Twig_DivExpression extends Twig_BinaryExpression
{
    public function operator($compiler)
    {
        return $compiler->raw('/');
    }
}


class Twig_ModExpression extends Twig_BinaryExpression
{
    public function operator($compiler)
    {
        return $compiler->raw('%');
    }
}


class Twig_CompareExpression extends Twig_Expression
{
    public $expr;
    public $ops;

    public function __construct($expr, $ops, $lineno)
    {
        parent::__construct($lineno);
        $this->expr = $expr;
        $this->ops = $ops;
    }

    public function compile($compiler)
    {
        $this->expr->compile($compiler);
        $i = 0;
        foreach ($this->ops as $op) {
            if ($i)
                $compiler->raw(' && ($tmp' . $i);
            list($op, $node) = $op;
            $compiler->raw(' ' . $op . ' ');
            $compiler->raw('($tmp' . ++$i . ' = ');
            $node->compile($compiler);
            $compiler->raw(')');
        }
        if ($i > 1)
            $compiler->raw(')');
    }
}


class Twig_UnaryExpression extends Twig_Expression
{
    public $node;

    public function __construct($node, $lineno)
    {
        parent::__construct($lineno);
        $this->node = $node;
    }

    public function compile($compiler)
    {
        $compiler->raw('(');
        $this->operator($compiler);
        $this->node->compile($compiler);
        $compiler->raw(')');
    }
}


class Twig_NotExpression extends Twig_UnaryExpression
{
    public function operator($compiler)
    {
        $compiler->raw('!');
    }
}


class Twig_NegExpression extends Twig_UnaryExpression
{
    public function operator($compiler)
    {
        $compiler->raw('-');
    }
}


class Twig_PosExpression extends Twig_UnaryExpression
{
    public function operator($compiler)
    {
        $compiler->raw('+');
    }
}


class Twig_Constant extends Twig_Expression
{
    public $value;

    public function __construct($value, $lineno)
    {
        parent::__construct($lineno);
        $this->value = $value;
    }

    public function compile($compiler)
    {
        $compiler->repr($this->value);
    }
}


class Twig_NameExpression extends Twig_Expression
{
    public $name;

    public function __construct($name, $lineno)
    {
        parent::__construct($lineno);
        $this->name = $name;
    }

    public function compile($compiler)
    {
        $compiler->format('(isset($context[\'%s\']) ? $context[\'%s\'] ' .
                  ': NULL)', $this->name, $this->name);
    }
}


class Twig_AssignNameExpression extends Twig_NameExpression
{

    public function compile($compiler)
    {
        $compiler->format('$context[\'%s\']', $this->name);
    }
}


class Twig_GetAttrExpression extends Twig_Expression
{
    public $node;
    public $attr;

    public function __construct($node, $attr, $lineno, $token_value)
    {
        parent::__construct($lineno);
        $this->node = $node;
        $this->attr = $attr;
        $this->token_value = $token_value;
    }

    public function compile($compiler)
    {
        $compiler->raw('twig_get_attribute(');
        $this->node->compile($compiler);
        $compiler->raw(', ');
        $this->attr->compile($compiler);
        if ($this->token_value == "[") # Don't look for functions if they're using foo[bar]
            $compiler->raw(', false');
        $compiler->raw(')');
    }
}


class Twig_MethodCallExpression extends Twig_Expression
{
    public $node;
    public $method;
    public $arguments;

    public function __construct($node, $method, $arguments, $lineno)
    {
        parent::__construct($lineno);
        $this->node = $node;
        $this->method = $method;
        $this->arguments = $arguments;
    }

    public function compile($compiler)
    {
        $compiler->raw('call_user_func(array(');
        $this->node->compile($compiler);
        $compiler->raw(', ');
        $this->method->compile($compiler);
        $compiler->raw(')');
        foreach ($this->arguments as $argument) {
            $compiler->raw(', ');
            $argument->compile($compiler);
        }
        $compiler->raw(')');
    }
}


class Twig_FilterExpression extends Twig_Expression
{
    public $node;
    public $filters;

    public function __construct($node, $filters, $lineno)
    {
        parent::__construct($lineno);
        $this->node = $node;
        $this->filters = $filters;
    }

    public function compile($compiler)
    {
        global $twig_filters;
        $postponed = array();
        for ($i = count($this->filters) - 1; $i >= 0; --$i) {
            list($name, $attrs) = $this->filters[$i];
            if (!isset($twig_filters[$name])) {
                $compiler->raw('twig_missing_filter(');
                $compiler->repr($name);
                $compiler->raw(', ');
            }
            else
                $compiler->raw($twig_filters[$name] . '(');
            $postponed[] = $attrs;
        }
        $this->node->compile($compiler);
        foreach (array_reverse($postponed) as $attributes) {
            foreach ($attributes as $node) {
                $compiler->raw(', ');
                $node->compile($compiler);
            }
            $compiler->raw(')');
        }
    }
}
