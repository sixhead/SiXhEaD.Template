<?php

declare(strict_types=1);

/**
 * SiXhEaD Template - PHP Template Engine
 *
 * Copyright (c) 2005-2026 Chairat Sunthornwiphat (sixhead.com)
 * Licensed under the MIT License. See the LICENSE file.
 *
 * Template syntax:
 * - `$name` is replaced with the value of variable `name`.
 * - `<!--{key}:{block}-->...<!--/{key}:{block}-->` marks a block. Blocks are
 *   hidden until applied. A hidden block that contains an applied child block
 *   stays visible so the child output is kept.
 * - `<!--sub:{n}-->...<!--/sub:{n}-->` marks part n inside a block. Each
 *   apply() uses the next part in turn.
 *
 * @version 6.3.0
 *
 * @phpstan-type BlockState array{content: string, hide: bool, apply: string, subs: array<int, string>, current_sub: int}
 */
class Template
{
    /** Path of the template file. */
    public string $tp_file;

    /** Key used in block markers, e.g. "template" for `<!--template:name-->`. */
    public string $tp_key;

    /**
     * Blocks registered from the template, keyed by block name.
     *
     * @var array<string, BlockState>
     */
    protected array $tp_block = [];

    /**
     * Values set with assign(), used when global mode is off.
     *
     * @var array<string, string|int|float|bool|Stringable|null>
     */
    protected array $tp_vars = [];

    /**
     * Variable names that are kept as literal `$name` text.
     *
     * @var array<string, true>
     */
    protected array $tp_ignore_vars = [];

    /** True reads values from `$GLOBALS`, false reads values from assign(). */
    protected bool $is_global = true;

    /** Raw template content. */
    protected string $all_content = '';

    /** Block selected with block(), used by apply(), hide() and sub(). */
    protected ?string $block_name = null;

    /**
     * Loads a template file and hides all of its blocks.
     *
     * @param string $tp_file Path of the template file.
     * @param string $tp_key  Key used in block markers.
     *
     * @throws RuntimeException When the file cannot be read or a block marker has no pair.
     */
    public function __construct(string $tp_file, string $tp_key = 'template')
    {
        $this->tp_file = $tp_file;
        $this->tp_key = $tp_key;
        $this->start();
    }

    /**
     * Reads the template file and registers its blocks.
     *
     * @throws RuntimeException When the file cannot be read or a block marker has no pair.
     */
    protected function start(): void
    {
        $content = is_file($this->tp_file) ? file_get_contents($this->tp_file) : false;
        if ($content === false) {
            throw new RuntimeException("Template file \"{$this->tp_file}\" not found");
        }
        $this->all_content = $content;
        $this->autoHide();
    }

    /**
     * Registers every block in the template as hidden.
     *
     * @throws RuntimeException When a closing block marker has no opening marker.
     */
    protected function autoHide(): void
    {
        preg_match_all('/<!--\/' . $this->quotedKey() . ':([^<>]+?)-->/', $this->all_content, $matches);
        foreach ($matches[1] as $block_name) {
            $this->tp_block[$block_name] = $this->newBlock($this->findBlockContent($block_name));
        }
    }

    /**
     * Keeps `$key` as literal text in the output instead of replacing it.
     *
     * @param string $key Variable name without `$`.
     */
    public function ignore(string $key): void
    {
        $this->tp_ignore_vars[$key] = true;
    }

    /**
     * Sets a value for `$key`. Used only when global mode is off.
     *
     * @param string $key Variable name without `$`.
     */
    public function assign(string $key, string|int|float|bool|Stringable|null $value): void
    {
        $this->tp_vars[$key] = $value;
    }

    /**
     * Selects where values come from.
     *
     * @param bool $is_global True reads `$GLOBALS`, false reads values set with assign().
     */
    public function setGlobal(bool $is_global): void
    {
        $this->is_global = $is_global;
    }

    /**
     * Selects a block and resets it to hidden with no applied output.
     *
     * @throws RuntimeException When the block is not in the template.
     */
    public function block(string $block_name): void
    {
        $content = $this->tp_block[$block_name]['content'] ?? $this->findBlockContent($block_name);
        $this->tp_block[$block_name] = $this->newBlock($content);
        $this->block_name = $block_name;
    }

    /**
     * Removes the applied output of a block and shows it as empty.
     *
     * @throws RuntimeException When the block is not in the template.
     */
    public function clear(string $block_name): void
    {
        $block = $this->getBlock($block_name);
        $block['hide'] = false;
        $block['apply'] = '';
        $this->tp_block[$block_name] = $block;
    }

    /**
     * Hides the selected block.
     *
     * @throws LogicException When no block is selected.
     */
    public function hide(): void
    {
        $block_name = $this->currentBlockName();
        $block = $this->getBlock($block_name);
        $block['hide'] = true;
        $this->tp_block[$block_name] = $block;
    }

    /**
     * Appends the selected block, with current values, to its output.
     *
     * @throws LogicException When no block is selected.
     */
    public function apply(): void
    {
        $block_name = $this->currentBlockName();
        $content = $this->applyBlock($block_name);
        $block = $this->getBlock($block_name);
        $block['hide'] = false;
        $block['apply'] .= $content;
        $this->tp_block[$block_name] = $block;
    }

    /**
     * Returns the block content with current values. Does not change the block output.
     *
     * @throws RuntimeException When the block is not in the template.
     */
    public function applyBlock(string $block_name): string
    {
        return $this->setValue($this->blockContent($block_name));
    }

    /**
     * Returns the raw block content. When sub() is active, returns the next sub part.
     *
     * @throws RuntimeException When the block is not in the template.
     */
    public function blockContent(string $block_name): string
    {
        $block = $this->getBlock($block_name);
        if ($block['subs'] === []) {
            return $block['content'];
        }
        $block['current_sub'] = $block['current_sub'] % count($block['subs']) + 1;
        $this->tp_block[$block_name] = $block;
        return $block['subs'][$block['current_sub']];
    }

    /**
     * Splits the selected block into sub parts 1..n. Zero turns sub parts off.
     *
     * @throws LogicException   When no block is selected.
     * @throws RuntimeException When a sub part is not in the block.
     */
    public function sub(int $all_sub_in_block): void
    {
        $block_name = $this->currentBlockName();
        $block = $this->getBlock($block_name);
        $block['subs'] = [];
        for ($no = 1; $no <= $all_sub_in_block; $no++) {
            if (!preg_match("/<!--SUB:{$no}-->(.*?)<!--\/SUB:{$no}-->/is", $block['content'], $matches)) {
                throw new RuntimeException("<!--sub:{$no}-->...<!--/sub:{$no}--> not found in block \"{$block_name}\"");
            }
            $block['subs'][$no] = $matches[1];
        }
        $block['current_sub'] = 0;
        $this->tp_block[$block_name] = $block;
    }

    /**
     * Returns sub part n of a block with current values. Does not change the sub turn.
     *
     * @throws RuntimeException When the block or sub part does not exist.
     */
    public function applySub(string $block_name, int $sub_no): string
    {
        return $this->setValue($this->subContent($block_name, $sub_no));
    }

    /**
     * Returns raw sub part n of a block.
     *
     * @throws RuntimeException When the block or sub part does not exist.
     */
    protected function subContent(string $block_name, int $sub_no): string
    {
        return $this->getBlock($block_name)['subs'][$sub_no]
            ?? throw new RuntimeException("Sub {$sub_no} not found in block \"{$block_name}\", call sub() first");
    }

    /**
     * Returns the sub part number that the next apply() uses.
     *
     * @throws LogicException When no block is selected.
     */
    public function getCurrentSub(): int
    {
        $block = $this->getBlock($this->currentBlockName());
        $next = $block['current_sub'] + 1;
        return $next > count($block['subs']) ? 1 : $next;
    }

    /**
     * Returns the number of sub parts in the selected block.
     *
     * @throws LogicException When no block is selected.
     */
    public function getCurrentSubTotal(): int
    {
        return count($this->getBlock($this->currentBlockName())['subs']);
    }

    /**
     * Returns the final content: blocks rendered, variables replaced, block markers removed.
     */
    public function generate(): string
    {
        $is_visible = false;
        $content = $this->render($this->all_content, $is_visible);
        return $this->replace('/<!--\/?' . $this->quotedKey() . ':[^<>]*?-->/', static fn(): string => '', $content);
    }

    /**
     * Prints the output of generate() without `<!-- tp ... -->` / `<!-- template ... -->`
     * comments and blank lines.
     */
    public function display(): void
    {
        $content = $this->generate();
        $content = $this->replace('@\n?<!-- (tp|template).*?-->@', static fn(): string => '', $content);
        $content = $this->replace("/(^[\r\n]*|[\r\n]+)\\s*[\r\n]+/", static fn(): string => "\n", $content);
        echo $content;
    }

    /**
     * Replaces blocks and variables in one pass, so applied output is never processed again.
     *
     * @param bool $is_visible Set to true when any block in $content produces output.
     */
    protected function render(string $content, bool &$is_visible): string
    {
        $key = $this->quotedKey();
        return $this->replace(
            "/<!--{$key}:([^<>]+?)-->(.*?)<!--\\/{$key}:\\1-->|\\$(\\w+)/s",
            function (array $matches) use (&$is_visible): string {
                if ($matches[3] !== null) {
                    return $this->varValue($matches[3]);
                }
                $output = $this->renderBlock((string) $matches[1], (string) $matches[2]);
                $is_visible = $is_visible || $output !== '';
                return $output;
            },
            $content,
            PREG_UNMATCHED_AS_NULL,
        );
    }

    /**
     * Returns the applied output of a block. A hidden block returns its rendered
     * content only when a child block inside it produces output.
     */
    protected function renderBlock(string $block_name, string $inner_content): string
    {
        $block = $this->tp_block[$block_name] ?? null;
        if ($block !== null && !$block['hide']) {
            return $block['apply'];
        }
        $has_visible_child = false;
        $content = $this->render($inner_content, $has_visible_child);
        return $has_visible_child ? $content : '';
    }

    /**
     * Replaces every `$name` in $content with its value.
     */
    protected function setValue(string $content): string
    {
        return $this->replace('/\$(\w+)/', fn(array $matches): string => $this->varValue((string) $matches[1]), $content);
    }

    /**
     * Returns the text for variable $name. Ignored names stay as `$name`.
     */
    protected function varValue(string $name): string
    {
        if (isset($this->tp_ignore_vars[$name])) {
            return '$' . $name;
        }
        $value = $this->is_global ? ($GLOBALS[$name] ?? null) : ($this->tp_vars[$name] ?? null);
        return match (true) {
            is_string($value) => $value,
            is_int($value), is_float($value), $value instanceof Stringable => (string) $value,
            is_bool($value) => $value ? '1' : '',
            default => '',
        };
    }

    /**
     * Runs preg_replace_callback() and throws on a PCRE error instead of returning null.
     *
     * @param callable(array<int|string, string|null>): string $callback
     *
     * @throws RuntimeException When PCRE fails, e.g. on the backtrack limit.
     */
    private function replace(string $pattern, callable $callback, string $subject, int $flags = 0): string
    {
        return preg_replace_callback($pattern, $callback, $subject, flags: $flags)
            ?? throw new RuntimeException('Template regex failed: ' . preg_last_error_msg());
    }

    /**
     * Returns the content between the markers of a block.
     *
     * @throws RuntimeException When the block is not in the template.
     */
    private function findBlockContent(string $block_name): string
    {
        $tag = $this->quotedKey() . ':' . preg_quote($block_name, '/');
        if (!preg_match("/<!--{$tag}-->(.*?)<!--\\/{$tag}-->/s", $this->all_content, $matches)) {
            throw $this->blockNotFound($block_name);
        }
        return $matches[1];
    }

    /**
     * @return BlockState
     */
    private function newBlock(string $content): array
    {
        return ['content' => $content, 'hide' => true, 'apply' => '', 'subs' => [], 'current_sub' => 0];
    }

    /**
     * @return BlockState
     *
     * @throws RuntimeException When the block is not in the template.
     */
    private function getBlock(string $block_name): array
    {
        return $this->tp_block[$block_name] ?? throw $this->blockNotFound($block_name);
    }

    private function blockNotFound(string $block_name): RuntimeException
    {
        return new RuntimeException("<!--{$this->tp_key}:{$block_name}-->...<!--/{$this->tp_key}:{$block_name}--> not found");
    }

    /**
     * @throws LogicException When no block is selected.
     */
    private function currentBlockName(): string
    {
        return $this->block_name ?? throw new LogicException('No block selected, call block() first');
    }

    private function quotedKey(): string
    {
        return preg_quote($this->tp_key, '/');
    }
}
