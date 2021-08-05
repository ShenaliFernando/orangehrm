<?php
/**
 * OrangeHRM is a comprehensive Human Resource Management (HRM) System that captures
 * all the essential functionalities required for any enterprise.
 * Copyright (C) 2006 OrangeHRM Inc., http://www.orangehrm.com
 *
 * OrangeHRM is free software; you can redistribute it and/or modify it under the terms of
 * the GNU General Public License as published by the Free Software Foundation; either
 * version 2 of the License, or (at your option) any later version.
 *
 * OrangeHRM is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
 * without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with this program;
 * if not, write to the Free Software Foundation, Inc., 51 Franklin Street, Fifth Floor,
 * Boston, MA  02110-1301, USA
 */

namespace OrangeHRM\Core\Authorization\Service;

use Exception;
use OrangeHRM\Admin\Service\UserService;
use OrangeHRM\Authentication\Service\AuthenticationService;
use OrangeHRM\Core\Authorization\Manager\AbstractUserRoleManager;
use OrangeHRM\Core\Exception\DaoException;
use OrangeHRM\Core\Exception\ServiceException;
use OrangeHRM\Core\Traits\ClassHelperTrait;
use OrangeHRM\Core\Traits\Service\ConfigServiceTrait;
use OrangeHRM\Entity\User;
use OrangeHRM\Framework\Logger;
use OrangeHRM\Framework\Services;

class UserRoleManagerService
{
    use ClassHelperTrait;
    use ConfigServiceTrait;

    public const KEY_USER_ROLE_MANAGER_CLASS = "authorize_user_role_manager_class";

    /**
     * @var AuthenticationService|null
     */
    protected ?AuthenticationService $authenticationService = null;

    /**
     * @return AuthenticationService
     */
    public function getAuthenticationService(): AuthenticationService
    {
        if (!$this->authenticationService instanceof AuthenticationService) {
            $this->authenticationService = new AuthenticationService();
        }
        return $this->authenticationService;
    }

    /**
     * @return UserService
     */
    public function getUserService(): UserService
    {
        return $this->getContainer()->get(Services::USER_SERVICE);
    }

    /**
     * @return string|null
     * @throws DaoException
     */
    public function getUserRoleManagerClassName(): ?string
    {
        return $this->getConfigService()->getConfigDao()->getValue(self::KEY_USER_ROLE_MANAGER_CLASS);
    }

    /**
     * @return AbstractUserRoleManager|null
     * @throws DaoException
     * @throws ServiceException
     */
    public function getUserRoleManager(): ?AbstractUserRoleManager
    {
        $logger = Logger::getLogger('core.UserRoleManagerService');

        $class = $this->getUserRoleManagerClassName();

        $manager = null;

        $fallbackNamespace = 'OrangeHRM\\Core\\Authorization\\Manager\\';
        if ($this->getClassHelper()->classExists($class, $fallbackNamespace)) {
            try {
                $class = $this->getClassHelper()->getClass($class, $fallbackNamespace);
                $manager = new $class();
            } catch (Exception $e) {
                throw new ServiceException('Exception when initializing user role manager:' . $e->getMessage());
            }
        } else {
            throw new ServiceException(sprintf('User Role Manager class %s not found.', $class));
        }

        if (!$manager instanceof AbstractUserRoleManager) {
            throw new ServiceException(
                sprintf('User Role Manager class %s is not a subclass of %s', $class, AbstractUserRoleManager::class)
            );
        }

        // Set System User object in manager
        $userId = $this->getAuthenticationService()->getLoggedInUserId();
        if (is_null($userId)) {
            throw new ServiceException('No logged in user found.');
        }
        $systemUser = $this->getUserService()->getSystemUser($userId);

        if ($systemUser instanceof User) {
            $manager->setUser($systemUser);
        } else {
            $logger->info('No logged in system user when creating UserRoleManager');
        }

        return $manager;
    }
}
