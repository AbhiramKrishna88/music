<?php

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2019
 */

namespace OCA\Music\Controller;

use \OCP\AppFramework\Controller;
use \OCP\AppFramework\Http\JSONResponse;
use \OCP\AppFramework\Http\TemplateResponse;
use \OCP\IRequest;
use \OCP\IURLGenerator;

use \OCA\Music\AppFramework\BusinessLayer\BusinessLayer;
use \OCA\Music\AppFramework\BusinessLayer\BusinessLayerException;
use \OCA\Music\AppFramework\Core\Logger;

use \OCA\Music\BusinessLayer\AlbumBusinessLayer;
use \OCA\Music\BusinessLayer\ArtistBusinessLayer;
use \OCA\Music\BusinessLayer\Library;
use \OCA\Music\BusinessLayer\PlaylistBusinessLayer;
use \OCA\Music\BusinessLayer\TrackBusinessLayer;

use \OCA\Music\Db\SortBy;

use \OCA\Music\Http\ErrorResponse;
use \OCA\Music\Http\FileResponse;

use \OCA\Music\Utility\CoverHelper;
use \OCA\Music\Utility\Util;

class SubsonicController extends Controller {
	const API_VERSION = '1.4.0';

	private $albumBusinessLayer;
	private $artistBusinessLayer;
	private $playlistBusinessLayer;
	private $trackBusinessLayer;
	private $library;
	private $urlGenerator;
	private $rootFolder;
	private $l10n;
	private $coverHelper;
	private $logger;

	public function __construct($appname,
								IRequest $request,
								$l10n,
								IURLGenerator $urlGenerator,
								AlbumBusinessLayer $albumBusinessLayer,
								ArtistBusinessLayer $artistBusinessLayer,
								PlaylistBusinessLayer $playlistBusinessLayer,
								TrackBusinessLayer $trackBusinessLayer,
								Library $library,
								$rootFolder,
								CoverHelper $coverHelper,
								Logger $logger) {
		parent::__construct($appname, $request);

		$this->albumBusinessLayer = $albumBusinessLayer;
		$this->artistBusinessLayer = $artistBusinessLayer;
		$this->playlistBusinessLayer = $playlistBusinessLayer;
		$this->trackBusinessLayer = $trackBusinessLayer;
		$this->library = $library;
		$this->urlGenerator = $urlGenerator;
		$this->l10n = $l10n;

		// used to deliver actual media file
		$this->rootFolder = $rootFolder;

		$this->coverHelper = $coverHelper;
		$this->logger = $logger;
	}

	/**
	 * @NoAdminRequired
	 * @PublicPage
	 * @NoCSRFRequired
	 * @SubsonicAPI
	 */
	public function handleRequest($method) {
		// TODO: handle user authentication here or with a separate middleware
		
		// Allow calling all methods with or without the postfix ".view"
		if (Util::endsWith($method, ".view")) {
			$method = \substr($method, 0, -\strlen(".view"));
		}
		
		// Allow calling ping or any of the getter functions in this class
		// with a matching REST URL
		if (($method === 'ping' || $method === 'download' || $method === 'stream' || Util::startsWith($method, 'get'))
				&& \method_exists($this, $method)) {
			return $this->$method();
		}
		else {
			$this->logger->log("Request $method not supported", 'warn');
			return self::subsonicErrorResonse(70, "Requested action $method is not supported");
		}
	}

	private function ping() {
		return self::subsonicResponse([]);
	}

	private function getLicense() {
		return self::subsonicResponse([
			'license' => [
				'valid' => 'true',
				'email' => '',
				licenseExpires => 'never'
			]
		]);
	}

	private function getMusicFolders() {
		return self::subsonicResponse([
			'musicFolders' => ['musicFolder' => [
					['id'=>'1', 'name'=>'Music']
			]]
		]);
	}

	private function getIndexes() {
		return self::subsonicResponse([
			'indexes' => ['index' => [
					['name'=>'A', 'artist'=>[
							['name'=>'ABBA', 'id'=>10], 
							['name'=>'ACDC', 'id'=>20]
						]
					]
				]
			]
		]);
	}

	private function getMusicDirectory() {
		$id = $this->request->getParam('id');
		if ($id == 100 || $id == 200) {
			// album songs
			return self::subsonicResponse([
				'directory' => [
					'id' => $id,
					'parent' => 10,
					'name' => 'First album',
					'child' => [
						['id' => '101', 'parent'=>$id, 'title'=>'Dancing Queen', 'album'=>'First album', 'artist'=>'ABBA', 
							'track'=>1, 'isDir'=>false, 'coverArt'=>123, 'genre'=>'Pop', 'year'=>1978, 'size'=>'123456',
							'contentType'=>'audio/mpeg', 'suffix'=>'mp3', 'duration'=>146, 'bitRate'=>'128', path=>'track101'
						], 
						['id' => '102', 'parent'=>$id, 'title'=>'Money, Money, Money', 'album'=>'First album', 'artist'=>'ABBA', 
							'track'=>2, 'isDir'=>false, 'coverArt'=>456, 'genre'=>'Pop', 'year'=>1978, 'size'=>'678123',
							'contentType'=>'audio/mpeg', 'suffix'=>'mp3', 'duration'=>146, 'bitRate'=>'128', path=>'track102'
						] 
					]
				]
			]);
		}
		else {
			// artist albums
			return self::subsonicResponse([
				'directory' => [
					'id' => 10,
					'parent' => 1,
					'name' => 'ABBA',
					'starred' => '2013-11-02T12:30:00',
					'child' => [
						['id'=>100, 'parent'=>10, 'title'=>'First album', 'artist'=>'ABBA', 'isDir'=>true, 'coverArt'=>123],
						['id'=>200, 'parent'=>10, 'title'=>'Another album', 'artist'=>'ABBA', 'isDir'=>true, 'coverArt'=>456],
					]
				]
			]);
		}
	}

	private function getAlbumList() {
		return self::subsonicResponse([
			'albumList' => ['album' => [
						['id' => '100', 'parent'=>10, 'title'=>'First album', 'artist'=>'ABBA', 'isDir'=>'true', 'coverArt'=>123, 'userRating'=>4, 'averageRating'=>4], 
						['id' => '200', 'parent'=>10, 'title'=>'Another album', 'artist'=>'ABBA', 'isDir'=>'true', 'coverArt'=>456, 'userRating'=>3, 'averageRating'=>5] 
					]
				]
			]
		);
	}

	private function getRandomSongs() {
		return self::subsonicResponse([
			'randomSongs' => ['song' => [
					['id' => '101', 'parent'=>100, 'title'=>'Dancing Queen', 'album'=>'First album', 'artist'=>'ABBA', 
						'track'=>1, 'isDir'=>'false', 'coverArt'=>123, 'genre'=>'Pop', 'year'=>1978, 'size'=>'123456',
						'contentType'=>'audio/mpeg', 'suffix'=>'mp3', 'duration'=>146, 'bitRate'=>'128', path=>'track101'
					], 
					['id' => '102', 'parent'=>100, 'title'=>'Money, Money, Money', 'album'=>'First album', 'artist'=>'ABBA', 
						'track'=>2, 'isDir'=>'false', 'coverArt'=>456, 'genre'=>'Pop', 'year'=>1978, 'size'=>'678123',
						'contentType'=>'audio/mpeg', 'suffix'=>'mp3', 'duration'=>146, 'bitRate'=>'128', path=>'track102'
					] 
				]
			]
		]);
	}

	private function getCoverArt() {
		$this->logger->log('Attempting to get cover', 'warn');
		
		$userId = 'root';
		$userFolder = $this->rootFolder->getUserFolder($userId);

		try {
			$coverData = $this->coverHelper->getCover(383018, $userId, $userFolder);
			if ($coverData !== null) {
				return new FileResponse($coverData);
			}
		} catch (BusinessLayerException $e) {
			return self::subsonicErrorResonse(70, 'album not found');
		}

		return self::subsonicErrorResonse(70, 'album has no cover');
	}

	private function stream() {
		// We don't support transcaoding, so 'stream' and 'download' act identically
		return $this->download();
	}

	private function download() {
		$userId = 'root';
		$trackId = 524301;
		
		try {
			$track = $this->trackBusinessLayer->find($trackId, $userId);
		} catch (BusinessLayerException $e) {
			return self::subsonicErrorResonse(70, $e->getMessage());
		}
		
		$files = $this->rootFolder->getUserFolder($userId)->getById($track->getFileId());
		
		if (\count($files) === 1) {
			return new FileResponse($files[0]);
		} else {
			return self::subsonicErrorResonse(70, 'file not found');
		}
	}

	private static function subsonicResponse($content) {
		$content['status'] = 'ok';
		$content['version'] = self::API_VERSION;
		return new JSONResponse(['subsonic-response' => $content]);
	}

	private static function subsonicErrorResonse($errorCode, $errorMessage) {
		return new JSONResponse([
			'subsonic-response' => [
				'version' => self::API_VERSION,
				'status' => 'failed',
				'error' => [
					'code' => $errorCode,
					'message' => $errorMessage
				]
			]
		]);
	}
}
