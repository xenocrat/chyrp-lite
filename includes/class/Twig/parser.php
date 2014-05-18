<?php
/**
 * Twig::Parser
 * ~~~~~~~~~~~~
 *
 * This module implements the Twig parser.
 *
 * :copyright: 2008 by Armin Ronacher.
 * :license: BSD.
 */


function twig_parse($source, $filename=NULL)
{
    $stream = twig_tokenize($source, $filename);
    $parser = new Twig_Parser($stream);
    return $parser->parse();
}


class Twig_Parser
{
    public $stream;
    public $blocks;
    public $extends;
    public $current_block;
    private $handlers;

    public function __construct($stream)
    {
        $this->stream = $stream;
        $this->extends = NULL;
        $this->blocks = array();
        $this->current_block = NULL;
        $this->handlers = array(
            'for' =>        array($this, 'parseForLoop'),
            'if' =>         array($this, 'parseIfCondition'),
            'extends' =>    array($this, 'parseExtends'),
            'include' =>    array($this, 'parseInclude'),
            'block' =>  array($this, 'parseBlock'),
            'super' =>  array($this, 'parseSuper'),

            # Chyrp specific extensions
            'url' =>    array($this, 'parseURL'),
            'admin' =>  array($this, 'parseAdminURL'),
            'paginate' =>   array($this, 'parsePaginate')
        );
    }

    public function parseForLoop($token)
    {
        $lineno = $token->lineno;
        list($is_multitarget, $item) = $this->parseAssignmentExpression();
        $this->stream->expect('in');
        $seq = $this->parseExpression();
        $this->stream->expect(Twig_Token::BLOCK_END_TYPE);
        $body = $this->subparse(array($this, 'decideForFork'));
        if ($this->stream->next()->value == 'else') {
            $this->stream->expect(Twig_Token::BLOCK_END_TYPE);
            $else = $this->subparse(array($this, 'decideForEnd'), true);
        }
        else
            $else = NULL;
        $this->stream->expect(Twig_Token::BLOCK_END_TYPE);
        return new Twig_ForLoop($is_multitarget, $item, $seq, $body, $else,
                    $lineno);
    }

    public function parsePaginate($token)
    {
        $lineno = $token->lineno;

        $per_page = $this->parseExpression();
        $as = $this->parseExpression();
        $this->stream->expect('in');
        $loop = $this->parseExpression();
        $this->stream->expect('as');
        $item = $this->parseExpression();
        $this->stream->expect(Twig_Token::BLOCK_END_TYPE);
        $body = $this->subparse(array($this, 'decidePaginateFork'));
        if ($this->stream->next()->value == 'else') {
            $this->stream->expect(Twig_Token::BLOCK_END_TYPE);
            $else = $this->subparse(array($this, 'decidePaginateEnd'), true);
        }
        else
            $else = NULL;
        $this->stream->expect(Twig_Token::BLOCK_END_TYPE);
        return new Twig_PaginateLoop($item, $per_page, 
                    $loop, $as, $body, $else, $lineno);
    }

    public function decideForFork($token)
    {
        return $token->test(array('else', 'endfor'));
    }

    public function decideForEnd($token)
    {
        return $token->test('endfor');
    }

    public function decidePaginateFork($token)
    {
        return $token->test(array('else', 'endpaginate'));
    }

    public function decidePaginateEnd($token)
    {
        return $token->test('endpaginate');
    }

    public function parseIfCondition($token)
    {
        $lineno = $token->lineno;
        $expr = $this->parseExpression();
        $this->stream->expect(Twig_Token::BLOCK_END_TYPE);
        $body = $this->subparse(array($this, 'decideIfFork'));
        $tests = array(array($expr, $body));
        $else = NULL;

        $end = false;
        while (!$end)
            switch ($this->stream->next()->value) {
            case 'else':
                $this->stream->expect(Twig_Token::BLOCK_END_TYPE);
                $else = $this->subparse(array($this, 'decideIfEnd'));
                break;
            case 'elseif':
                $expr = $this->parseExpression();
                $this->stream->expect(Twig_Token::BLOCK_END_TYPE);
                $body = $this->subparse(array($this, 'decideIfFork'));
                $tests[] = array($expr, $body);
                break;
            case 'endif':
                $end = true;
                break;
            }

        $this->stream->expect(Twig_Token::BLOCK_END_TYPE);
        return new Twig_IfCondition($tests, $else, $lineno);
    }

    public function decideIfFork($token)
    {
        return $token->test(array('elseif', 'else', 'endif'));
    }

    public function decideIfEnd($token)
    {
        return $token->test(array('endif'));
    }

    public function parseBlock($token)
    {
        $lineno = $token->lineno;
        $name = $this->stream->expect(Twig_Token::NAME_TYPE)->value;
        if (isset($this->blocks[$name]))
            throw new Twig_SyntaxError("block '$name' defined twice.",
                           $lineno);
        $this->current_block = $name;
        $this->stream->expect(Twig_Token::BLOCK_END_TYPE);
        $body = $this->subparse(array($this, 'decideBlockEnd'), true);
        $this->stream->expect(Twig_Token::BLOCK_END_TYPE);
        $block = new Twig_Block($name, $body, $lineno);
        $this->blocks[$name] = $block;
        $this->current_block = NULL;
        return new Twig_BlockReference($name, $lineno);
    }

    public function decideBlockEnd($token)
    {
        return $token->test('endblock');
    }

    public function parseExtends($token)
    {
        $lineno = $token->lineno;
        if (!is_null($this->extends))
            throw new Twig_SyntaxError('multiple extend tags', $lineno);
        $this->extends = $this->stream->expect(Twig_Token::STRING_TYPE)->value;
        $this->stream->expect(Twig_Token::BLOCK_END_TYPE);
        return NULL;
    }

    public function parseInclude($token)
    {
        $expr = $this->parseExpression();
        $this->stream->expect(Twig_Token::BLOCK_END_TYPE);
        return new Twig_Include($expr, $token->lineno);
    }

    public function parseSuper($token)
    {
        if (is_null($this->current_block))
            throw new Twig_SyntaxError('super outside block', $token->lineno);
        $this->stream->expect(Twig_Token::BLOCK_END_TYPE);
        return new Twig_Super($this->current_block, $token->lineno);
    }

    public function parseURL($token)
    {
        $expr = $this->parseExpression();

        if ($this->stream->test("in")) {
            $this->parseExpression();
            $cont = $this->parseExpression();
        } else
            $cont = null;

        $this->stream->expect(Twig_Token::BLOCK_END_TYPE);

        return new Twig_URL($expr, $cont, $token->lineno);
    }

    public function parseAdminURL($token)
    {
        $expr = $this->parseExpression();
        $this->stream->expect(Twig_Token::BLOCK_END_TYPE);
        return new Twig_AdminURL($expr, $token->lineno);
    }

    public function parseExpression()
    {
        return $this->parseConditionalExpression();
    }

    public function parseConditionalExpression()
    {
        $lineno = $this->stream->current->lineno;
        $expr1 = $this->parseOrExpression();
        while ($this->stream->test(Twig_Token::OPERATOR_TYPE, '?')) {
            $this->stream->next();
            $expr2 = $this->parseOrExpression();
            $this->stream->expect(Twig_Token::OPERATOR_TYPE, ':');
            $expr3 = $this->parseConditionalExpression();
            $expr1 = new Twig_ConditionalExpression($expr1, $expr2, $expr3,
                                $this->lineno);
            $lineno = $this->stream->current->lineno;
        }
        return $expr1;
    }

    public function parseOrExpression()
    {
        $lineno = $this->stream->current->lineno;
        $left = $this->parseAndExpression();
        while ($this->stream->test('or')) {
            $this->stream->next();
            $right = $this->parseAndExpression();
            $left = new Twig_OrExpression($left, $right, $lineno);
            $lineno = $this->stream->current->lineno;
        }
        return $left;
    }

    public function parseAndExpression()
    {
        $lineno = $this->stream->current->lineno;
        $left = $this->parseCompareExpression();
        while ($this->stream->test('and')) {
            $this->stream->next();
            $right = $this->parseCompareExpression();
            $left = new Twig_AndExpression($left, $right, $lineno);
            $lineno = $this->stream->current->lineno;
        }
        return $left;
    }

    public function parseCompareExpression()
    {
        static $operators = array('==', '!=', '<', '>', '>=', '<=');
        $lineno = $this->stream->current->lineno;
        $expr = $this->parseAddExpression();
        $ops = array();
        while ($this->stream->test(Twig_Token::OPERATOR_TYPE, $operators))
            $ops[] = array($this->stream->next()->value,
                       $this->parseAddExpression());

        if (empty($ops))
            return $expr;
        return new Twig_CompareExpression($expr, $ops, $lineno);
    }

    public function parseAddExpression()
    {
        $lineno = $this->stream->current->lineno;
        $left = $this->parseSubExpression();
        while ($this->stream->test(Twig_Token::OPERATOR_TYPE, '+')) {
            $this->stream->next();
            $right = $this->parseSubExpression();
            $left = new Twig_AddExpression($left, $right, $lineno);
            $lineno = $this->stream->current->lineno;
        }
        return $left;
    }

    public function parseSubExpression()
    {
        $lineno = $this->stream->current->lineno;
        $left = $this->parseConcatExpression();
        while ($this->stream->test(Twig_Token::OPERATOR_TYPE, '-')) {
            $this->stream->next();
            $right = $this->parseConcatExpression();
            $left = new Twig_SubExpression($left, $right, $lineno);
            $lineno = $this->stream->current->lineno;
        }
        return $left;
    }

    public function parseConcatExpression()
    {
        $lineno = $this->stream->current->lineno;
        $left = $this->parseMulExpression();
        while ($this->stream->test(Twig_Token::OPERATOR_TYPE, '~')) {
            $this->stream->next();
            $right = $this->parseMulExpression();
            $left = new Twig_ConcatExpression($left, $right, $lineno);
            $lineno = $this->stream->current->lineno;
        }
        return $left;
    }

    public function parseMulExpression()
    {
        $lineno = $this->stream->current->lineno;
        $left = $this->parseDivExpression();
        while ($this->stream->test(Twig_Token::OPERATOR_TYPE, '*')) {
            $this->stream->next();
            $right = $this->parseDivExpression();
            $left = new Twig_MulExpression($left, $right, $lineno);
            $lineno = $this->stream->current->lineno;
        }
        return $left;
    }

    public function parseDivExpression()
    {
        $lineno = $this->stream->current->lineno;
        $left = $this->parseModExpression();
        while ($this->stream->test(Twig_Token::OPERATOR_TYPE, '/')) {
            $this->stream->next();
            $right = $this->parseModExpression();
            $left = new Twig_DivExpression($left, $right, $lineno);
            $lineno = $this->stream->current->lineno;
        }
        return $left;
    }

    public function parseModExpression()
    {
        $lineno = $this->stream->current->lineno;
        $left = $this->parseUnaryExpression();
        while ($this->stream->test(Twig_Token::OPERATOR_TYPE, '%')) {
            $this->stream->next();
            $right = $this->parseUnaryExpression();
            $left = new Twig_ModExpression($left, $right, $lineno);
            $lineno = $this->stream->current->lineno;
        }
        return $left;
    }

    public function parseUnaryExpression()
    {
        if ($this->stream->test('not'))
            return $this->parseNotExpression();
        if ($this->stream->current->type == Twig_Token::OPERATOR_TYPE) {
            switch ($this->stream->current->value) {
            case '-':
                return $this->parseNegExpression();
            case '+':
                return $this->parsePosExpression();
            }
        }
        return $this->parsePrimaryExpression();
    }

    public function parseNotExpression()
    {
        $token = $this->stream->next();
        $node = $this->parseUnaryExpression();
        return new Twig_NotExpression($node, $token->lineno);
    }

    public function parseNegExpression()
    {
        $token = $this->stream->next();
        $node = $this->parseUnaryExpression();
        return new Twig_NegExpression($node, $token->lineno);
    }

    public function parsePosExpression()
    {
        $token = $this->stream->next();
        $node = $this->parseUnaryExpression();
        return new Twig_PosExpression($node, $token->lineno);
    }

    public function parsePrimaryExpression($assignment=false)
    {
        $token = $this->stream->current;
        switch ($token->type) {
        case Twig_Token::NAME_TYPE:
            $this->stream->next();
            switch ($token->value) {
            case 'true':
                $node = new Twig_Constant(true, $token->lineno);
                break;
            case 'false':
                $node = new Twig_Constant(false, $token->lineno);
                break;
            case 'none':
                $node = new Twig_Constant(NULL, $token->lineno);
                break;
            default:
                $cls = $assignment ? 'Twig_AssignNameExpression'
                           : 'Twig_NameExpression';
                $node = new $cls($token->value, $token->lineno);
            }
            break;
        case Twig_Token::NUMBER_TYPE:
        case Twig_Token::STRING_TYPE:
            $this->stream->next();
            $node = new Twig_Constant($token->value, $token->lineno);
            break;
        default:
            if ($token->test(Twig_Token::OPERATOR_TYPE, '(')) {
                $this->stream->next();
                $node = $this->parseExpression();
                $this->stream->expect(Twig_Token::OPERATOR_TYPE, ')');
            }
            else
                throw new Twig_SyntaxError('unexpected token',
                               $token->lineno);
        }
        if (!$assignment)
            $node = $this->parsePostfixExpression($node);
        return $node;
    }

    public function parsePostfixExpression($node)
    {
        $stop = false;
        while (!$stop && $this->stream->current->type ==
                  Twig_Token::OPERATOR_TYPE)
            switch ($this->stream->current->value) {
            case '.':
            case '[':
                $node = $this->parseSubscriptExpression($node);
                break;
            case '|':
                $node = $this->parseFilterExpression($node);
                break;
            default:
                $stop = true;
                break;
            }
        return $node;
    }

    public function parseSubscriptExpression($node)
    {
        $token = $this->stream->next();
        $lineno = $token->lineno;
        if ($token->value == '.') {
            $token = $this->stream->next();
            if ($token->type == Twig_Token::NAME_TYPE ||
                $token->type == Twig_Token::NUMBER_TYPE)
                $arg = new Twig_Constant($token->value, $lineno);
            else
                throw new Twig_SyntaxError('expected name or number',
                               $lineno);
        }
        else {
            $arg = $this->parseExpression();
            $this->stream->expect(Twig_Token::OPERATOR_TYPE, ']');
        }

        if (!$this->stream->test(Twig_Token::OPERATOR_TYPE, '('))
            return new Twig_GetAttrExpression($node, $arg, $lineno, $token->value);

        /* sounds like something wants to call a member with some
           arguments.  Let's parse the parameters */
        $this->stream->next();
        $arguments = array();
        while (!$this->stream->test(Twig_Token::OPERATOR_TYPE, ')')) {
            if (count($arguments))
                $this->stream->expect(Twig_Token::OPERATOR_TYPE, ',');
            $arguments[] = $this->parseExpression();
        }
        $this->stream->expect(Twig_Token::OPERATOR_TYPE, ')');
        return new Twig_MethodCallExpression($node, $arg, $arguments, $lineno);
    }

    public function parseFilterExpression($node)
    {
        $lineno = $this->stream->current->lineno;
        $filters = array();
        while ($this->stream->test(Twig_Token::OPERATOR_TYPE, '|')) {
            $this->stream->next();
            $token = $this->stream->expect(Twig_Token::NAME_TYPE);
            $args = array();
            if ($this->stream->test(
                Twig_Token::OPERATOR_TYPE, '(')) {
                $this->stream->next();
                while (!$this->stream->test(
                    Twig_Token::OPERATOR_TYPE, ')')) {
                    if (!empty($args))
                        $this->stream->expect(
                        Twig_Token::OPERATOR_TYPE, ',');
                    $args[] = $this->parseExpression();
                }
                $this->stream->expect(Twig_Token::OPERATOR_TYPE, ')');
            }
            $filters[] = array($token->value, $args);
        }
        return new Twig_FilterExpression($node, $filters, $lineno);
    }

    public function parseAssignmentExpression()
    {
        $lineno = $this->stream->current->lineno;
        $targets = array();
        $is_multitarget = false;
        while (true) {
            if (!empty($targets))
                $this->stream->expect(Twig_Token::OPERATOR_TYPE, ',');
            if ($this->stream->test(Twig_Token::OPERATOR_TYPE, ')') ||
                $this->stream->test(Twig_Token::VAR_END_TYPE) ||
                $this->stream->test(Twig_Token::BLOCK_END_TYPE) ||
                $this->stream->test('in'))
                break;
            $targets[] = $this->parsePrimaryExpression(true);
            if (!$this->stream->test(Twig_Token::OPERATOR_TYPE, ','))
                break;
            $is_multitarget = true;
        }
        if (!$is_multitarget && count($targets) == 1)
            return array(false, $targets[0]);
        return array(true, $targets);
    }

    public function subparse($test, $drop_needle=false)
    {
        $lineno = $this->stream->current->lineno;
        $rv = array();
        while (!$this->stream->eof) {
            switch ($this->stream->current->type) {
            case Twig_Token::TEXT_TYPE:
                $token = $this->stream->next();
                $rv[] = new Twig_Text($token->value, $token->lineno);
                break;
            case Twig_Token::VAR_START_TYPE:
                $token = $this->stream->next();
                $expr = $this->parseExpression();
                $this->stream->expect(Twig_Token::VAR_END_TYPE);
                $rv[] = new Twig_Print($expr, $token->lineno);
                break;
            case Twig_Token::BLOCK_START_TYPE:
                $this->stream->next();
                $token = $this->stream->current;
                if ($token->type !== Twig_Token::NAME_TYPE)
                    throw new Twig_SyntaxError('expected directive',
                                               $token->lineno);
                if (!is_null($test) && call_user_func($test, $token)) {
                    if ($drop_needle)
                        $this->stream->next();
                    return Twig_NodeList::fromArray($rv, $lineno);
                }
                if (!isset($this->handlers[$token->value]))
                    throw new Twig_SyntaxError('unknown directive',
                                   $token->lineno);
                $this->stream->next();
                $node = call_user_func($this->handlers[$token->value],
                               $token);
                if (!is_null($node))
                    $rv[] = $node;
                break;
            default:
                assert(false, 'Lexer or parser ended up in ' .
                       'unsupported state.');
            }
        }

        return Twig_NodeList::fromArray($rv, $lineno);
    }

    public function parse()
    {
        try {
            $body = $this->subparse(NULL);
        }
        catch (Twig_SyntaxError $e) {
            if (is_null($e->filename))
                $e->filename = $this->stream->filename;
            throw $e;
        }
        if (!is_null($this->extends))
            foreach ($this->blocks as $block)
                $block->parent = $this->extends;
        return new Twig_Module($body, $this->extends, $this->blocks,
                       $this->stream->filename);
    }
}
