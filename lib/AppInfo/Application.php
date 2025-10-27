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

namespace OCA\ShareListing\AppInfo;

use OCA\Files\Event\LoadSidebar;
use OCA\ShareListing\Listener\LoadSidebarScript;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;

include_once __DIR__ . '/../../vendor/autoload.php';

/**
 * @psalm-api
 */
class Application extends App implements IBootstrap {

	public const appID = 'sharelisting';

	public function __construct() {
		parent::__construct(self::appID);
	}

	public function register(IRegistrationContext $context): void {
		$context->registerEventListener(LoadSidebar::class, LoadSidebarScript::class);
		// TODO: Implement register() method.
	}

	public function boot(IBootContext $context): void {
		// TODO: Implement boot() method.
	}


}
