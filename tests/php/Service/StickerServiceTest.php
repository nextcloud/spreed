<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Service;

use OCA\Talk\Config;
use OCA\Talk\Service\StickerService;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\IUserFolder;
use OCP\Files\NotFoundException;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class StickerServiceTest extends TestCase {
	protected Config&MockObject $talkConfig;
	protected IRootFolder&MockObject $rootFolder;
	protected IUserFolder&MockObject $userFolder;
	protected StickerService $service;

	public function setUp(): void {
		parent::setUp();

		$this->talkConfig = $this->createMock(Config::class);
		$this->rootFolder = $this->createMock(IRootFolder::class);
		$this->userFolder = $this->createMock(IUserFolder::class);
		$this->userFolder->method('getRelativePath')
			->willReturnCallback(static fn (string $path): string => substr($path, strlen('/alice/files')));
		$this->rootFolder->method('getUserFolder')
			->with('alice')
			->willReturn($this->userFolder);

		$this->service = new StickerService($this->talkConfig, $this->rootFolder);
	}

	protected function file(int $id, string $path, string $mimetype, bool $shareable = true): File&MockObject {
		$file = $this->createMock(File::class);
		$file->method('getId')->willReturn($id);
		$file->method('getName')->willReturn(basename($path));
		$file->method('getPath')->willReturn('/alice/files' . $path);
		$file->method('getMimetype')->willReturn($mimetype);
		$file->method('isShareable')->willReturn($shareable);
		$file->method('getEtag')->willReturn('etag' . $id);
		return $file;
	}

	/**
	 * @param list<File|Folder> $children
	 */
	protected function folder(int $id, string $name, array $children): Folder&MockObject {
		$folder = $this->createMock(Folder::class);
		$folder->method('getId')->willReturn($id);
		$folder->method('getName')->willReturn($name);
		$folder->method('getDirectoryListing')->willReturn($children);
		return $folder;
	}

	public function testDisabledWithoutFolder(): void {
		$this->talkConfig->method('getStickersFolder')->willReturn('');
		$this->rootFolder->expects($this->never())->method('getUserFolder');

		$this->assertSame([], $this->service->getPacks('alice'));
	}

	public function testNoPacksWithoutAccessToTheFolder(): void {
		$this->talkConfig->method('getStickersFolder')->willReturn('Stickers');
		$this->userFolder->method('get')
			->with('Stickers')
			->willThrowException(new NotFoundException());

		$this->assertSame([], $this->service->getPacks('alice'));
	}

	public function testNoPacksWhenThePathIsAFile(): void {
		$this->talkConfig->method('getStickersFolder')->willReturn('Stickers');
		$this->userFolder->method('get')
			->willReturn($this->file(1, '/Stickers', 'image/png'));

		$this->assertSame([], $this->service->getPacks('alice'));
	}

	public function testPacksFromSubfolders(): void {
		$this->talkConfig->method('getStickersFolder')->willReturn('Stickers');
		$cats = $this->folder(20, 'cats', [
			$this->file(22, '/Stickers/cats/sleepy.gif', 'image/gif'),
			$this->file(21, '/Stickers/cats/Angry.webp', 'image/webp'),
		]);
		$dogs = $this->folder(30, 'Dogs', [
			// Not an image clients show inline
			$this->file(31, '/Stickers/Dogs/bark.svg', 'image/svg+xml'),
			// Not shareable by the user
			$this->file(32, '/Stickers/Dogs/wag.png', 'image/png', false),
			// Packs do not nest
			$this->folder(33, 'puppies', [$this->file(34, '/Stickers/Dogs/puppies/a.png', 'image/png')]),
		]);
		$root = $this->folder(10, 'Stickers', [
			$dogs,
			$this->file(12, '/Stickers/wave.jpg', 'image/jpeg'),
			$cats,
			$this->file(11, '/Stickers/notes.txt', 'text/plain'),
		]);
		$this->userFolder->method('get')
			->with('Stickers')
			->willReturn($root);

		$this->assertSame([
			[
				'id' => 10,
				'name' => 'Stickers',
				'stickers' => [
					['id' => 12, 'name' => 'wave', 'path' => '/Stickers/wave.jpg', 'mimetype' => 'image/jpeg', 'etag' => 'etag12'],
				],
			],
			[
				'id' => 20,
				'name' => 'cats',
				'stickers' => [
					['id' => 21, 'name' => 'Angry', 'path' => '/Stickers/cats/Angry.webp', 'mimetype' => 'image/webp', 'etag' => 'etag21'],
					['id' => 22, 'name' => 'sleepy', 'path' => '/Stickers/cats/sleepy.gif', 'mimetype' => 'image/gif', 'etag' => 'etag22'],
				],
			],
		], $this->service->getPacks('alice'));
	}
}
