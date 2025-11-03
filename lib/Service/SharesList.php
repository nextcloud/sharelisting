<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: 2018 Nextcloud GmbH
// SPDX-FileCopyrightText: 2022 Solution Libre SAS
// SPDX-FileContributor: Roeland Jago Douma <roeland@famdouma.nl>
// SPDX-FileContributor: Florent Poinsaut <florent@solution-libre.fr>
// SPDX-FileContributor: John Molakvoæ <skjnldsv@protonmail.com>

namespace OCA\ShareListing\Service;

use EmptyIterator;
use Iterator;
use OC\User\NoUserException;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Share;
use OCP\Share\IManager as ShareManager;
use OCP\Share\IShare;
use Symfony\Component\Serializer\Encoder\CsvEncoder;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Serializer;
use Throwable;
use function iter\filter;
use function iter\map;

final class SharesList {

	public const FILTER_NONE = 0;
	public const FILTER_OWNER = 1;
	public const FILTER_INITIATOR = 2;
	public const FILTER_RECIPIENT = 3;
	public const FILTER_TOKEN = 4;
	public const FILTER_HAS_EXPIRATION = 5;
	public const FILTER_NO_EXPIRATION = 6;

	public function __construct(
		private ShareManager $shareManager,
		private IRootFolder $rootFolder,
	) {
	}

	/**
	 * @return non-empty-array<IShare::TYPE_*>
	 */
	private function getShareTypes(): array {
		return [
			IShare::TYPE_USER,
			IShare::TYPE_GROUP,
			IShare::TYPE_LINK,
			IShare::TYPE_EMAIL,
			IShare::TYPE_REMOTE,
		];
	}

	/**
	 * @return Iterator<IShare>
	 */
	public function get(?string $userId, int $filter, ?string $path = null, ?string $token = null): Iterator {
		/** @var iterable<IShare> $shares */
		$shares = $this->getShares($userId);

		// If path is set. Filter for the current user
		if ($path !== null) {
			if ($userId === null) {
				throw new \RuntimeException('Unable to query a path if no user is set.');
			}
			$userFolder = $this->rootFolder->getUserFolder($userId);
			try {
				$node = $userFolder->get($path);
			} catch (NotFoundException) {
				// Path is not valid for user so nothing to report;
				return new EmptyIterator();
			}
			$shares = filter(function (IShare $share) use ($node) {
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
			$shares = filter(fn (IShare $share) => $share->getShareOwner() === $userId, $shares);
		}
		if ($filter === self::FILTER_INITIATOR) {
			$shares = filter(fn (IShare $share) => $share->getSharedBy() === $userId, $shares);
		}
		if ($filter === self::FILTER_RECIPIENT) {
			// We can't check the recipient since this might be a group share etc. However you can't share to yourself
			$shares = filter(fn (IShare $share) => $share->getShareOwner() !== $userId && $share->getSharedBy() !== $userId, $shares);
		}

		if ($filter === self::FILTER_HAS_EXPIRATION) {
			$shares = filter(fn (IShare $share): bool => $share->getExpirationDate() !== null, $shares);
		}

		if ($filter === self::FILTER_NO_EXPIRATION) {
			$shares = filter(fn (IShare $share): bool => $share->getExpirationDate() === null, $shares);
		}

		$shares = filter(function (IShare $share): bool {
			try {
				$userFolder = $this->rootFolder->getUserFolder($share->getShareOwner());
			} catch (NoUserException) {
				return false;
			} catch (Throwable) {
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
	 * @return Iterator<IShare>
	 */
	public function getSub(string $userId, int $filter, string $path): Iterator {
		/** @var iterable<IShare> $shares */
		$shares = $this->shareManager->getAllShares();

		// If path is set. Filter for the current user
		$userFolder = $this->rootFolder->getUserFolder($userId);
		try {
			$node = $userFolder->get($path);
		} catch (NotFoundException) {
			// Path is not valid for user so nothing to report;
			return new EmptyIterator();
		}

		$shares = filter(function (IShare $share) use ($node): bool {
			if ($node->getId() === $share->getNodeId()) {
				return false;
			}
			if ($node instanceof Folder) {
				return !empty($node->getById($share->getNodeId()));
			}
			return false;
		}, $shares);

		if ($filter === self::FILTER_OWNER) {
			$shares = filter(fn (IShare $share): bool => $share->getShareOwner() === $userId, $shares);
		}
		if ($filter === self::FILTER_INITIATOR) {
			$shares = filter(fn (IShare $share): bool => $share->getSharedBy() === $userId, $shares);
		}
		if ($filter === self::FILTER_RECIPIENT) {
			// We can't check the recipient since this might be a group share etc. However you can't share to yourself
			$shares = filter(fn (IShare $share): bool => $share->getShareOwner() !== $userId && $share->getSharedBy() !== $userId, $shares);
		}

		return filter(function (IShare $share): bool {
			try {
				$userFolder = $this->rootFolder->getUserFolder($share->getShareOwner());
			} catch (Throwable) {
				return false;
			}
			$nodes = $userFolder->getById($share->getNodeId());

			return $nodes !== [];
		}, $shares);
	}

	public function getFormattedShares(?string $userId = null, int $filter = self::FILTER_NONE, ?string $path = null, ?string $token = null): Iterator {
		$shares = $this->get($userId, $filter, $path, $token);

		$formattedShares = map(fn (IShare $share): array => $this->formatShare($share), $shares);

		return $formattedShares;
	}

	private function getShares(?string $userId): Iterator {
		if ($userId === null) {
			/** @var iterable<IShare> $shares */
			$shares = $this->shareManager->getAllShares();
		} else {
			$shareTypes = $this->getShareTypes();

			foreach ($shareTypes as $shareType) {
				$shares = $this->shareManager->getSharesBy($userId, $shareType, null, true, -1, 0);

				if ($shareType !== IShare::TYPE_LINK) {
					foreach ($shares as $share) {
						yield $share;
					}

					$shares = $this->shareManager->getSharedWith($userId, $shareType, null, -1, 0);
				}
			}
		}

		foreach ($shares as $share) {
			yield $share;
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
		if (!empty($nodes)) {
			$node = array_shift($nodes);
			$data['path'] = $userFolder->getRelativePath($node->getPath());
			$data['name'] = $node->getName();
			$data['is_directory'] = $node->getType() === 'dir';
		}

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

		$expirationDate = $share->getExpirationDate();
		if ($expirationDate !== null) {
			$data['expiration'] = $expirationDate->format('Y-m-d H:i:s');
		}

		return $data;
	}

	public function filterStringToInt(?string $filterString): int {
		return match ($filterString) {
			'owner' => SharesList::FILTER_OWNER,
			'initiator' => SharesList::FILTER_INITIATOR,
			'recipient' => SharesList::FILTER_RECIPIENT,
			'has-expiration' => SharesList::FILTER_HAS_EXPIRATION,
			'no-expiration' => SharesList::FILTER_NO_EXPIRATION,
			default => SharesList::FILTER_NONE,
		};
	}

	public function getSerializedShares(array $shares, ?string $format = 'json'): string {
		switch ($format) {
			case 'csv':
				$encoders = [new CsvEncoder()];
				$context = [];
				break;
			default:
				$encoders = [new JsonEncoder()];
				$format = 'json';
				$context = ['json_encode_options' => JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE];
				break;
		}

		$serializer = new Serializer([], $encoders);
		return $serializer->serialize($shares, $format, $context);
	}
}
