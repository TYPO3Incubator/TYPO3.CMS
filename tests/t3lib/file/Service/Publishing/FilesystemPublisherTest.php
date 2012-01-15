<?php
/***************************************************************
 *  Copyright notice
 *
 *  (c) 2011 Andreas Wolf <andreas.wolf@ikt-werk.de>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *  A copy is found in the textfile GPL.txt and important notices to the license
 *  from the author is found in LICENSE.txt distributed with these scripts.
 *
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

require_once 'PublisherBaseTest.php';

/**
 * Test for the filesystem resource publisher
 *
 * @author Andreas Wolf <andreas.wolf@ikt-werk.de>
 * @package TYPO3
 * @subpackage t3lib
 */
class t3lib_file_Service_Publishing_FilesystemPublisherTest extends t3lib_file_Service_Publishing_PublisherBaseTest {

	/**
	 * @var t3lib_file_Service_Publishing_FilesystemPublisher
	 */
	private $fixture;

	protected function prepareFixture($configuration, t3lib_file_Storage $publishingTarget = NULL) {
		if (!$publishingTarget) {
			$publishingTarget = $this->getMock('t3lib_file_Storage', array(), array(), '', FALSE);
		}

		$this->fixture = new t3lib_file_Service_Publishing_FilesystemPublisher($publishingTarget, $configuration);
	}

	protected function getFileMock($identifier) {
		$mock = $this->getMock('t3lib_file_File', array(), array(), '', FALSE);
		$mock->expects($this->any())->method('getIdentifier')->will($this->returnValue($identifier));

		return $mock;
	}

	protected function getFolderMock($identifier, array $files = array(), array $subfolders = array()) {
		$mock = $this->getMock('t3lib_file_Folder', array(), array(), '', FALSE);
		$mock->expects($this->any())->method('getIdentifier')->will($this->returnValue($identifier));
		$mock->expects($this->any())->method('getFiles')->will($this->returnValue($files));
		$mock->expects($this->any())->method('getSubfolders')->will($this->returnValue($subfolders));

		return $mock;
	}

	/**
	 * @test
	 * @group integration
	 */
	public function publishFileCopiesFileToPublishingTargetAndReturnsCorrectUrl() {
		$identifier = '/some/file/path.jpg';
		$baseUri = 'http://example.org/';

		$mockedFile = $this->getMock('t3lib_file_File', array(), array(), '', FALSE);
		$mockedFile->expects($this->any())->method('getIdentifier')->will($this->returnValue($identifier));
		$mockedTarget = $this->getMock('t3lib_file_Storage', array(), array(), '', FALSE);
		$mockedTarget->expects($this->once())->method('copyFile')->with($this->equalTo($mockedFile), $this->equalTo($identifier));
		$mockedTarget->expects($this->any())->method('getBaseUri')->will($this->returnValue($baseUri));

		$this->prepareFixture(array('baseUri' => $baseUri), $mockedTarget);

		$exportedUri = $this->fixture->publishFile($mockedFile);
		$this->assertEquals($baseUri . ltrim($identifier, '/'), $exportedUri);
	}

	/**
	 * @test
	 */
	public function publishFileDoesNotPublishAlreadyPublishedFilesAgain() {
		$mockedFile = $this->getMock('t3lib_file_File', array(), array(), '', FALSE);
		$mockedTarget = $this->getMock('t3lib_file_Storage', array(), array(), '', FALSE);
		$mockedTarget->expects($this->never())->method('copyFile');
		$mockedTarget->expects($this->atLeastOnce())->method('hasFile')->will($this->returnValue(TRUE));

		$this->prepareFixture(array('baseUri' => 'http://example.org/'), $mockedTarget);

		$this->fixture->publishFile($mockedFile);
	}

	/**
	 * @test
	 */
	public function publishFolderPublishesAllFilesInFolder() {
		$mockedFile1 = $this->getFileMock('/file1');
		$mockedFile2 = $this->getFileMock('/file2');

		$mockedFolder = $this->getFolderMock('/', array($mockedFile1, $mockedFile2), array());

		/** @var $fixture t3lib_file_Service_Publishing_FilesystemPublisher */
		$fixture = $this->getMock('t3lib_file_Service_Publishing_FilesystemPublisher', array('publishFile'), array(), '', FALSE);
		// TODO change this to check for the exact file objects as soon as PHPUnit supports object matching in with()
		$fixture->expects($this->exactly(2))->method('publishFile');

		$fixture->publishFolder($mockedFolder);
	}

	/**
	 * @test
	 */
	public function publishFolderPublishesAllFilesInSubFolders() {
		$mockedFile1 = $this->getFileMock('/file1');
		$mockedFile2 = $this->getFileMock('/file2');

		$mockedSubfolder = $this->getFolderMock('/subdir/', array($mockedFile2), array());
		$mockedFolder = $this->getFolderMock('/', array($mockedFile1), array($mockedSubfolder));

		/** @var $fixture t3lib_file_Service_Publishing_FilesystemPublisher */
		$fixture = $this->getMock('t3lib_file_Service_Publishing_FilesystemPublisher', array('publishFile'), array(), '', FALSE);
		// TODO change this to check for the exact file objects as soon as PHPUnit supports object matching in with()
		$fixture->expects($this->exactly(2))->method('publishFile');

		$fixture->publishFolder($mockedFolder);
	}

	/**
	 * @test
	 */
	public function publishingRespectsBasedirIfSet() {
		$identifier = '/some/file/path.jpg';
		$baseDir = 'someDir';

		$mockedFile = $this->getMock('t3lib_file_File', array(), array(), '', FALSE);
		$mockedFile->expects($this->any())->method('getIdentifier')->will($this->returnValue($identifier));
		list(, $mockedTarget) = $this->mockTarget();
		$mockedTarget->expects($this->once())->method('copyFile')->with($this->equalTo($mockedFile), $this->equalTo('/' . $baseDir . $identifier));

		$this->prepareFixture(array('baseDir' => $baseDir), $mockedTarget);

		$this->fixture->publishFile($mockedFile);
	}
}
