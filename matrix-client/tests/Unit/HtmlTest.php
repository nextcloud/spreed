<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Nextcloud\Matrix\Tests\Unit;

use Nextcloud\Matrix\Html\HtmlToMarkdown;
use Nextcloud\Matrix\Html\MarkdownToHtml;
use Nextcloud\Matrix\Html\Sanitizer;
use PHPUnit\Framework\TestCase;

final class HtmlTest extends TestCase {
	public function testSanitizerRemovesDangerousContent(): void {
		$html = '<mx-reply><blockquote>old</blockquote></mx-reply><p onclick="x()">Hi <script>alert(1)</script><a href="javascript:alert(1)">bad</a> <a href="https://ok.org" target="_blank">ok</a> <img src="https://tracker/pixel.gif" alt="pix"> <img src="mxc://hs/id" alt="cat"> <marquee>text</marquee></p>';
		$clean = Sanitizer::sanitize($html);
		self::assertStringNotContainsString('mx-reply', $clean);
		self::assertStringNotContainsString('script', $clean);
		self::assertStringNotContainsString('javascript:', $clean);
		self::assertStringNotContainsString('onclick', $clean);
		self::assertStringNotContainsString('tracker', $clean);
		self::assertStringNotContainsString('marquee', $clean);
		self::assertStringContainsString('<a href="https://ok.org" target="_blank">ok</a>', $clean);
		self::assertStringContainsString('<img src="mxc://hs/id" alt="cat">', $clean);
		self::assertStringContainsString('pix', $clean);
		self::assertStringContainsString('text', $clean);
	}

	public function testHtmlToMarkdown(): void {
		$c = new HtmlToMarkdown();
		self::assertSame('Hello **world** and *it* and ~~no~~ `code`', $c->convert('Hello <strong>world</strong> and <em>it</em> and <del>no</del> <code>code</code>'));
		self::assertSame("Line 1\nLine 2", $c->convert('Line 1<br>Line 2'));
		self::assertSame("Para 1\n\nPara 2", $c->convert('<p>Para 1</p><p>Para 2</p>'));
		self::assertSame("# Title\n\n- one\n- two\n\n1. a\n2. b", $c->convert('<h1>Title</h1><ul><li>one</li><li>two</li></ul><ol><li>a</li><li>b</li></ol>'));
		self::assertSame("> quoted\n> more\n\nafter", $c->convert('<blockquote>quoted<br>more</blockquote>after'));
		self::assertSame("```php\necho 1;\n```", $c->convert('<pre><code class="language-php">echo 1;</code></pre>'));
		self::assertSame('[Nextcloud](https://nextcloud.com) https://x.org', $c->convert('<a href="https://nextcloud.com">Nextcloud</a> <a href="https://x.org">https://x.org</a>'));
		self::assertSame('reply text', $c->convert('<mx-reply><blockquote><a href="https://matrix.to/#/!r:hs/$e">In reply to</a> <a href="https://matrix.to/#/@a:hs">@a:hs</a><br>old</blockquote></mx-reply>reply text'));
		self::assertSame('a \\* b \\_ c', $c->convert('a * b _ c'));
		self::assertSame("| a | b |\n| --- | --- |\n| 1 | 2 |", $c->convert('<table><tr><th>a</th><th>b</th></tr><tr><td>1</td><td>2</td></tr></table>'));
	}

	public function testPillResolver(): void {
		$c = new HtmlToMarkdown();
		$c->setPillResolver(static fn (string $userId, string $text) => $userId === '@alice:hs' ? '@"alice"' : null);
		self::assertSame('hi @"alice" and Bob', $c->convert('hi <a href="https://matrix.to/#/@alice:hs">Alice</a> and <a href="https://matrix.to/#/@bob:hs">Bob</a>'));
		self::assertSame('hi @"alice"', $c->convert('hi <a href="matrix:u/alice:hs">Alice</a>'));
	}

	public function testMarkdownToHtml(): void {
		$c = new MarkdownToHtml();
		self::assertNull($c->convert('plain text'));
		self::assertNull($c->convert("two\nlines"));
		self::assertNull($c->convert('quotes "and" <angles> stay plain'));
		self::assertSame('a<br><strong>b</strong>', $c->convert("a\n**b**"));
		self::assertSame('Hello <strong>world</strong> and <em>it</em> and <del>no</del> <code>x</code>', $c->convert('Hello **world** and *it* and ~~no~~ `x`'));
		self::assertSame('<h1>Title</h1><ul><li>one</li><li>two</li></ul><ol><li>a</li><li>b</li></ol>', $c->convert("# Title\n\n- one\n- two\n\n1. a\n2. b"));
		self::assertSame('<blockquote><p>quoted<br>more</p></blockquote><p>after</p>', $c->convert("> quoted\n> more\n\nafter"));
		self::assertSame('<pre><code class="language-php">echo 1 &lt; 2;</code></pre>', $c->convert("```php\necho 1 < 2;\n```"));
		self::assertSame('<a href="https://nextcloud.com">Nextcloud</a> <a href="https://x.org">https://x.org</a>', $c->convert('[Nextcloud](https://nextcloud.com) https://x.org'));
		self::assertSame('a &lt;b&gt; <strong>c</strong>', $c->convert('a <b> **c**'));
		self::assertSame('<ul><li>a<ul><li>b</li></ul></li><li>c</li></ul>', $c->convert("- a\n  - b\n- c"));
		self::assertSame('<code>**not bold**</code> <strong>bold</strong>', $c->convert('`**not bold**` **bold**'));
	}

	public function testMarkdownInlineHook(): void {
		$c = new MarkdownToHtml();
		$c->setInlineHook(static fn (string $token) => $token === '@"alice"' ? '<a href="https://matrix.to/#/@alice:hs">Alice</a>' : null);
		self::assertSame('hi <a href="https://matrix.to/#/@alice:hs">Alice</a> and @bob', $c->convert('hi @"alice" and @bob'));
	}

	public function testRoundTrip(): void {
		$md = "Hello **world**\n\n- one\n- two\n\n> quote\n\n```\ncode\n```";
		$html = (new MarkdownToHtml())->convert($md);
		self::assertNotNull($html);
		self::assertSame($md, (new HtmlToMarkdown())->convert($html));
	}
}
