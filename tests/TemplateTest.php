<?php

declare(strict_types=1);

namespace SiXhEaD\Template\Tests;

use LogicException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Stringable;
use Template;

final class TemplateTest extends TestCase
{
    /** @var list<string> */
    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            unlink($file);
        }
        $this->files = [];
    }

    private function template(string $content, string $key = 'template'): Template
    {
        $file = tempnam(sys_get_temp_dir(), 'tp_');
        self::assertIsString($file);
        file_put_contents($file, $content);
        $this->files[] = $file;
        $tp = new Template($file, $key);
        $tp->setGlobal(false);
        return $tp;
    }

    public function testMissingFileThrows(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Template file "/no/such/file.html" not found');
        new Template('/no/such/file.html');
    }

    public function testDirectoryPathThrows(): void
    {
        $this->expectException(RuntimeException::class);
        new Template(sys_get_temp_dir());
    }

    public function testEmptyTemplateGeneratesEmptyString(): void
    {
        self::assertSame('', $this->template('')->generate());
    }

    public function testAssignedValuesReplaceVariables(): void
    {
        $tp = $this->template('<h1>$title</h1><p>$missing</p>');
        $tp->assign('title', 'Hello');
        self::assertSame('<h1>Hello</h1><p></p>', $tp->generate());
    }

    public function testGlobalModeReadsGlobals(): void
    {
        $GLOBALS['tp_test_title'] = 'From global';
        try {
            $tp = $this->template('$tp_test_title');
            $tp->setGlobal(true);
            $tp->assign('tp_test_title', 'From assign');
            self::assertSame('From global', $tp->generate());
        } finally {
            unset($GLOBALS['tp_test_title']);
        }
    }

    public function testGlobalNonScalarValueBecomesEmpty(): void
    {
        $GLOBALS['tp_test_list'] = ['a', 'b'];
        try {
            $tp = $this->template('[$tp_test_list]');
            $tp->setGlobal(true);
            self::assertSame('[]', $tp->generate());
        } finally {
            unset($GLOBALS['tp_test_list']);
        }
    }

    public function testValueTypesAreConvertedToString(): void
    {
        $tp = $this->template('$int|$float|$yes|$no|$null|$object');
        $tp->assign('int', 42);
        $tp->assign('float', 1.5);
        $tp->assign('yes', true);
        $tp->assign('no', false);
        $tp->assign('null', null);
        $tp->assign('object', new class implements Stringable {
            public function __toString(): string
            {
                return 'stringable';
            }
        });
        self::assertSame('42|1.5|1|||stringable', $tp->generate());
    }

    public function testLongestVariableNameWins(): void
    {
        $tp = $this->template('$css-$css_uc');
        $tp->assign('css', 'info');
        $tp->assign('css_uc', 'Info');
        self::assertSame('info-Info', $tp->generate());
    }

    public function testIgnoreKeepsOnlyExactVariableName(): void
    {
        $tp = $this->template('$no $note $1000 $library() $$()');
        $tp->assign('no', 'x');
        $tp->assign('note', 'memo');
        $tp->ignore('no');
        $tp->ignore('1000');
        $tp->ignore('library');
        self::assertSame('$no memo $1000 $library() $$()', $tp->generate());
    }

    public function testIgnoreAppliesInsideBlocks(): void
    {
        $tp = $this->template('<!--template:row-->$jQuery $no;<!--/template:row-->');
        $tp->ignore('jQuery');
        $tp->block('row');
        $tp->assign('no', 1);
        $tp->apply();
        self::assertSame('$jQuery 1;', $tp->generate());
    }

    public function testBlocksAreHiddenByDefault(): void
    {
        $tp = $this->template('a<!--template:x-->hidden<!--/template:x-->b');
        self::assertSame('ab', $tp->generate());
    }

    public function testApplyRepeatsBlockWithCurrentValues(): void
    {
        $tp = $this->template("<ul>\n<!--template:li--><li>\$no</li>\n<!--/template:li--></ul>");
        $tp->block('li');
        foreach ([1, 2, 3] as $no) {
            $tp->assign('no', $no);
            $tp->apply();
        }
        self::assertSame("<ul>\n<li>1</li>\n<li>2</li>\n<li>3</li>\n</ul>", $tp->generate());
    }

    public function testAppliedValuesAreNotReplacedAgain(): void
    {
        $tp = $this->template('<!--template:x-->$a<!--/template:x-->|$b');
        $tp->assign('a', '$b');
        $tp->assign('b', 'B');
        $tp->block('x');
        $tp->apply();
        self::assertSame('$b|B', $tp->generate());
    }

    public function testLiteralPercentEncodedTextIsKept(): void
    {
        $tp = $this->template('<a href="?q=a%0Ab%09c%0D">link</a>');
        self::assertSame('<a href="?q=a%0Ab%09c%0D">link</a>', $tp->generate());
    }

    public function testCrlfTemplateKeepsLineEndings(): void
    {
        $tp = $this->template("<!--template:x-->\r\n\t\$v\r\n<!--/template:x-->");
        $tp->block('x');
        $tp->assign('v', 'ok');
        $tp->apply();
        self::assertSame("\r\n\tok\r\n", $tp->generate());
    }

    public function testBlockNotFoundThrows(): void
    {
        $tp = $this->template('nothing');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('<!--template:data-->...<!--/template:data--> not found');
        $tp->block('data');
    }

    public function testClosingMarkerWithoutOpeningThrows(): void
    {
        $this->expectException(RuntimeException::class);
        $this->template('text<!--/template:orphan-->');
    }

    public function testApplyWithoutBlockThrows(): void
    {
        $tp = $this->template('<!--template:x-->x<!--/template:x-->');
        $this->expectException(LogicException::class);
        $tp->apply();
    }

    public function testHideRemovesAppliedBlock(): void
    {
        $tp = $this->template('<!--template:x-->x<!--/template:x-->');
        $tp->block('x');
        $tp->apply();
        $tp->hide();
        self::assertSame('', $tp->generate());
    }

    public function testBlockResetsPreviousOutput(): void
    {
        $tp = $this->template('<!--template:x-->$v<!--/template:x-->');
        $tp->block('x');
        $tp->assign('v', 'old');
        $tp->apply();
        $tp->block('x');
        $tp->assign('v', 'new');
        $tp->apply();
        self::assertSame('new', $tp->generate());
    }

    public function testClearShowsBlockAsEmpty(): void
    {
        $tp = $this->template('[<!--template:x-->x<!--/template:x-->]');
        $tp->block('x');
        $tp->apply();
        $tp->clear('x');
        self::assertSame('[]', $tp->generate());
    }

    public function testClearUnknownBlockThrows(): void
    {
        $tp = $this->template('nothing');
        $this->expectException(RuntimeException::class);
        $tp->clear('x');
    }

    public function testApplyBlockReturnsContentWithoutChangingBlock(): void
    {
        $tp = $this->template('$out<!--template:x-->[$v]<!--/template:x-->');
        $tp->assign('v', 'value');
        $tp->assign('out', $tp->applyBlock('x') . $tp->applyBlock('x'));
        self::assertSame('[value][value]', $tp->generate());
    }

    public function testBlockContentReturnsRawContent(): void
    {
        $tp = $this->template('<!--template:x--><b>$v</b><!--/template:x-->');
        self::assertSame('<b>$v</b>', $tp->blockContent('x'));
    }

    public function testSubCyclesThroughParts(): void
    {
        $tp = $this->template('<!--template:x--><!--sub:1-->A$no <!--/sub:1--><!--SUB:2-->B$no <!--/SUB:2--><!--/template:x-->');
        $tp->block('x');
        $tp->sub(2);
        self::assertSame(2, $tp->getCurrentSubTotal());
        $next = [];
        for ($no = 1; $no <= 3; $no++) {
            $next[] = $tp->getCurrentSub();
            $tp->assign('no', $no);
            $tp->apply();
        }
        self::assertSame([1, 2, 1], $next);
        self::assertSame(2, $tp->getCurrentSub());
        self::assertSame('A1 B2 A3 ', $tp->generate());
    }

    public function testSubZeroUsesWholeBlock(): void
    {
        $tp = $this->template('<!--template:x-->all<!--/template:x-->');
        $tp->block('x');
        $tp->sub(0);
        self::assertSame(0, $tp->getCurrentSubTotal());
        self::assertSame(1, $tp->getCurrentSub());
        $tp->apply();
        self::assertSame('all', $tp->generate());
    }

    public function testSubNotFoundThrows(): void
    {
        $tp = $this->template('<!--template:x--><!--sub:1-->a<!--/sub:1--><!--/template:x-->');
        $tp->block('x');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('<!--sub:2-->...<!--/sub:2--> not found in block "x"');
        $tp->sub(2);
    }

    public function testApplySubReturnsOnePart(): void
    {
        $tp = $this->template('<!--template:x--><!--sub:1-->a$v<!--/sub:1--><!--sub:2-->b$v<!--/sub:2--><!--/template:x-->');
        $tp->block('x');
        $tp->sub(2);
        $tp->assign('v', '!');
        self::assertSame('b!', $tp->applySub('x', 2));
        self::assertSame(1, $tp->getCurrentSub());
    }

    public function testApplySubWithoutSubThrows(): void
    {
        $tp = $this->template('<!--template:x-->a<!--/template:x-->');
        $this->expectException(RuntimeException::class);
        $tp->applySub('x', 1);
    }

    public function testSubStateBelongsToItsBlock(): void
    {
        $tp = $this->template('<!--template:a--><!--sub:1-->a1<!--/sub:1--><!--sub:2-->a2<!--/sub:2--><!--/template:a--><!--template:b-->b<!--/template:b-->');
        $tp->block('a');
        $tp->sub(2);
        self::assertSame('b', $tp->applyBlock('b'));
        self::assertSame('a1', $tp->applyBlock('a'));
    }

    public function testHiddenParentIsKeptWhenChildIsApplied(): void
    {
        $tp = $this->template('<!--template:list-->$title<ul><!--template:item--><li>$no</li><!--/template:item--></ul><!--/template:list-->');
        $tp->assign('title', 'T');
        $tp->block('item');
        foreach ([1, 2] as $no) {
            $tp->assign('no', $no);
            $tp->apply();
        }
        self::assertSame('T<ul><li>1</li><li>2</li></ul>', $tp->generate());
    }

    public function testHiddenParentIsRemovedWhenChildIsEmpty(): void
    {
        $tp = $this->template('[<!--template:list-->$title<!--template:item-->$no<!--/template:item--><!--/template:list-->]');
        self::assertSame('[]', $tp->generate());
        $tp->block('item');
        $tp->apply();
        $tp->clear('item');
        self::assertSame('[]', $tp->generate());
    }

    public function testAppliedParentUsesItsOwnOutput(): void
    {
        $tp = $this->template('<!--template:p-->($v<!--template:c-->c<!--/template:c-->)<!--/template:p-->');
        $tp->block('c');
        $tp->apply();
        $tp->block('p');
        $tp->assign('v', 'p');
        $tp->apply();
        self::assertSame('(pc)', $tp->generate());
    }

    public function testCustomKeyWithRegexCharacters(): void
    {
        $tp = $this->template('<!--my.key+1:x-->$v<!--/my.key+1:x--><!--template:y-->y<!--/template:y-->', 'my.key+1');
        $tp->block('x');
        $tp->assign('v', 'ok');
        $tp->apply();
        self::assertSame('ok<!--template:y-->y<!--/template:y-->', $tp->generate());
    }

    public function testGenerateCanBeCalledAgainAfterMoreApplies(): void
    {
        $tp = $this->template('<!--template:x-->$no<!--/template:x-->');
        $tp->block('x');
        $tp->assign('no', 1);
        $tp->apply();
        self::assertSame('1', $tp->generate());
        $tp->assign('no', 2);
        $tp->apply();
        self::assertSame('12', $tp->generate());
    }

    public function testDisplayRemovesDevCommentsAndBlankLines(): void
    {
        $tp = $this->template("<p>\$v</p>\n<!-- tp note -->\n\n   \n<!-- template note -->\n<!-- keep -->\n<b>end</b>");
        $tp->assign('v', 'x');
        $this->expectOutputString("<p>x</p>\n<!-- keep -->\n<b>end</b>");
        $tp->display();
    }

    public function testSubclassCanOverrideDisplay(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'tp_');
        self::assertIsString($file);
        file_put_contents($file, '$v');
        $this->files[] = $file;
        $tp = new class ($file) extends Template {
            public function display(): void
            {
                echo strtoupper($this->generate());
            }
        };
        $tp->setGlobal(false);
        $tp->assign('v', 'sub');
        $this->expectOutputString('SUB');
        $tp->display();
    }
}
