<?php
declare(strict_types=1);
/**
 * @copyright Copyright (c) 2018 Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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

namespace OCA\ShareListing\Service;

use iter;
use OC\User\NoUserException;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\IUserManager;
use OCP\Share;
use OCP\Share\IManager as ShareManager;
use OCP\Share\IShare;

class SharesList {

	const FILTER_NONE = 0;
	const FILTER_OWNER = 1;
	const FILTER_INITIATOR = 2;
	const FILTER_RECIPIENT = 3;
	const FILTER_TOKEN = 4;

	/** @var ShareManager */
	private $shareManager;

	/** @var IUserManager */
	private $userManager;

	/** @var IRootFolder */
	private $rootFolder;

	public function __construct(ShareManager $shareManager,
								IUserManager $userManager,
								IRootFolder $rootFolder) {
		$this->shareManager = $shareManager;
		$this->userManager = $userManager;
		$this->rootFolder = $rootFolder;
	}

	private function getShareTypes(): array {
		return [
			IShare::TYPE_USER,
			IShare::TYPE_GROUP,
			IShare::TYPE_LINK,
			IShare::TYPE_EMAIL,
			IShare::TYPE_REMOTE,
		];
	}

	public function get(string $userId, int $filter, string $path = null, string $token = null): \Iterator {
		$shares = $this->getShares($userId);

		// If path is set. Filter for the current user
		if ($path !== null) {
			$userFolder = $this->rootFolder->getUserFolder($userId);
			try {
				$node = $userFolder->get($path);
			} catch (NotFoundException $e) {
				// Path is not valid for user so nothing to report;
				return new \EmptyIterator();
			}
			$shares = iter\filter(function (IShare $share) use ($node) {
				if ($node->getId() === $share->getNodeId()) {
					return true;
				}
				if ($node instanceof Folder) {
					return !empty($node->getById($share->getNodeId()));
				}
				return false;
			}, $shares);
		}
		if ($token !== null) {
                        $shares = [$this->shareManager->getShareByToken($token)];
		}

		if ($filter === self::FILTER_OWNER) {
			$shares = iter\filter(function (IShare $share) use ($userId) {
				return $share->getShareOwner() === $userId;
			}, $shares);
		}
		if ($filter === self::FILTER_INITIATOR) {
			$shares = iter\filter(function (IShare $share) use ($userId) {
				return $share->getSharedBy() === $userId;
			}, $shares);
		}
		if ($filter === self::FILTER_RECIPIENT) {
			// We can't check the recipient since this might be a group share etc. However you can't share to yourself
			$shares = iter\filter(function (IShare $share) use ($userId) {
				return $share->getShareOwner() !== $userId && $share->getSharedBy() !== $userId;
			}, $shares);
		}

		$shares = iter\filter(function (IShare $share) {
			try {
				$userFolder = $this->rootFolder->getUserFolder($share->getShareOwner());
			} catch (NoUserException $e) {
				return false;
			} catch (\Throwable $e) {
				return false;
			}
			$nodes = $userFolder->getById($share->getNodeId());

			return $nodes !== [];
		}, $shares);

		return $shares;
	}

	/**
	 * Get all subshares of the current path
	 * Get all shares. And filter them by being a subpath of the current path.
	 * This allows us to build a list of subfiles/folder that are shared
	 * as well
	 */
	public function getSub(string $userId, int $filter, string $path): \Iterator {
		$shares = $this->shareManager->getAllShares();

		// If path is set. Filter for the current user
		$userFolder = $this->rootFolder->getUserFolder($userId);
		try {
			$node = $userFolder->get($path);
		} catch (NotFoundException $e) {
			// Path is not valid for user so nothing to report;
			return new \EmptyIterator();
		}

		$shares = iter\filter(function (IShare $share) use ($node) {
			if ($node->getId() === $share->getNodeId()) {
				return false;
			}
			if ($node instanceof Folder) {
				return !empty($node->getById($share->getNodeId()));
			}
			return false;
		}, $shares);

		if ($filter === self::FILTER_OWNER) {
			$shares = iter\filter(function (IShare $share) use ($userId) {
				return $share->getShareOwner() === $userId;
			}, $shares);
		}
		if ($filter === self::FILTER_INITIATOR) {
			$shares = iter\filter(function (IShare $share) use ($userId) {
				return $share->getSharedBy() === $userId;
			}, $shares);
		}
		if ($filter === self::FILTER_RECIPIENT) {
			// We can't check the recipient since this might be a group share etc. However you can't share to yourself
			$shares = iter\filter(function (IShare $share) use ($userId) {
				return $share->getShareOwner() !== $userId && $share->getSharedBy() !== $userId;
			}, $shares);
		}

		$shares = iter\filter(function (IShare $share) {
			try {
				$userFolder = $this->rootFolder->getUserFolder($share->getShareOwner());
			} catch (NoUserException $e) {
				return false;
			} catch (\Throwable $e) {
				return false;
			}
			$nodes = $userFolder->getById($share->getNodeId());

			return $nodes !== [];
		}, $shares);

		return $shares;
	}

	public function getFormattedShares(string $userId, int $filter, string $path = null, string $token = null): \Iterator {
		$shares = $this->get($userId, $filter, $path, $token);

		$formattedShares = iter\map(function (IShare $share) {
			return $this->formatShare($share);
		}, $shares);

		return $formattedShares;
	}

	private function getShares(string $userId): \Iterator {
		$shareTypes = $this->getShareTypes();

		foreach ($shareTypes as $shareType) {
			$shares = $this->shareManager->getSharesBy($userId, $shareType, null, true, -1, 0);

			foreach ($shares as $share) {
				yield $share;
			}

			if ($shareType !== \OCP\Share\IShare::TYPE_LINK) {
				$shares = $this->shareManager->getSharedWith($userId, $shareType, null, -1, 0);
				foreach ($shares as $share) {
					yield $share;
				}
			}
		}
	}

	public function formatShare(IShare $share): array {
		$userFolder = $this->rootFolder->getUserFolder($share->getShareOwner());

		$data = [
			'id' => $share->getId(),
			'file_id' => $share->getNodeId(),
			'owner' => $share->getShareOwner(),
			'initiator' => $share->getSharedBy(),
			'time' => $share->getShareTime()->format(\DATE_ATOM),
			'permissions' => $share->getPermissions(),
		];

		$nodes = $userFolder->getById($share->getNodeId());
		$node = array_shift($nodes);
		$data['path'] = $userFolder->getRelativePath($node->getPath());
		$data['name'] = $node->getName();
		$data['is_directory'] = $node->getType() === 'dir';



		if ($share->getShareType() === IShare::TYPE_USER) {
			$data['type'] = 'user';
			$data['recipient'] = $share->getSharedWith();
		}
		if ($share->getShareType() === IShare::TYPE_GROUP) {
			$data['type'] = 'group';
			$data['recipient'] = $share->getSharedWith();
		}
		if ($share->getShareType() === IShare::TYPE_LINK) {
			$data['type'] = 'link';
			$data['token'] = $share->getToken();
		}
		if ($share->getShareType() === IShare::TYPE_EMAIL) {
			$data['type'] = 'email';
			$data['recipient'] = $share->getSharedWith();
			$data['token'] = $share->getToken();
		}
		if ($share->getShareType() === IShare::TYPE_REMOTE) {
			$data['type'] = 'federated';
			$data['recipient'] = $share->getSharedWith();
		}

		return $data;
	}
}
