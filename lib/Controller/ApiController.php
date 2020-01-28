<?php
declare(strict_types=1);
/**
 * @copyright Copyright (c) 2019 John Molakvoæ <skjnldsv@protonmail.com>
 *
 * @author John Molakvoæ <skjnldsv@protonmail.com>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\ShareListing\Controller;

use iter;
use OCA\ShareListing\Service\SharesList;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSException;
use OCP\AppFramework\OCS\OCSNotFoundException;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Share;
use OCP\Share\IShare;

class ApiController extends OCSController {

	/** @var IUserSession */
	protected $userSession;

	/** @var IUserManager */
	private $userManager;

	/** @var SharesList */
	protected $sharesList;

	/**
	 * @param string $appName
	 * @param IRequest $request
	 * @param IUserSession $userSession
	 * @param IManager $userManager
	 * @param SharesList $sharesList
	 */
	public function __construct(string $appName,
								IRequest $request,
								IUserSession $userSession,
								IUserManager $userManager,
								SharesList $sharesList) {
		parent::__construct($appName, $request);

		$this->userSession = $userSession;
		$this->userManager = $userManager;
		$this->sharesList = $sharesList;
	}

	/**
	 * @NoAdminRequired
	 *
	 * Get shared sub folders of a fiven path
	 *
	 * @param string $path path of the current folder
	 * @return DataResponse
	 */
	public function getSharedSubfolders(string $path): DataResponse {
		$currentUser = $this->userSession->getUser();

		// Check if the target user exists
		if ($currentUser === null) {
			throw new OCSNotFoundException('User does not exist');
		}

		$shares = $this->sharesList->getSub($currentUser->getUID(), SharesList::FILTER_NONE, $path);

		// format results
		$formattedShares = iter\map(function (IShare $share) {
			return $this->sharesList->formatShare($share);
		}, $shares);

		// remove current folder
		$filteredShares = iter\filter(function($share) use ($path) {
			return $share['path'] !== $path;
		}, $formattedShares);

		// sort directories first
		$sortedShares = iter\toArray($filteredShares);
		usort($sortedShares, function($a, $b) {
			if ($a['is_directory'] && $b['is_directory']) {
				return strcmp($a['path'], $b['path']);
			}
			return $b['is_directory'] - $a['is_directory'];
		});

		return new DataResponse($sortedShares);
	}
}
