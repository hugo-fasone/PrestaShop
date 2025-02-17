<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/OSL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to https://devdocs.prestashop.com/ for more information.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

namespace PrestaShopBundle\Security\Admin;

use PrestaShop\PrestaShop\Adapter\LegacyContext;
use PrestaShopBundle\Entity\Employee\Employee;
use PrestaShopBundle\Entity\Repository\TabRepository;
use PrestaShopBundle\Entity\Tab;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

/**
 * This handle is called when the employee successfully logs in to the back office, its purpose is
 * to dynamically set the route to redirect to based on the Employee's configured homepage.
 */
class AdminAuthenticationSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    use TargetPathTrait;

    public function __construct(
        private readonly RouterInterface $router,
        private readonly Security $security,
        private readonly TabRepository $tabRepository,
        private readonly LegacyContext $legacyContext,
    ) {
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): ?Response
    {
        if ($request->hasPreviousSession()) {
            $redirectUrl = $this->getTargetPath($request->getSession(), 'main');
        }
        if (empty($redirectUrl)) {
            $redirectUrl = $this->getHomepageUrl();
        }
        if (empty($redirectUrl)) {
            $redirectUrl = $this->router->generate('admin_homepage');
        }

        return new RedirectResponse($redirectUrl);
    }

    private function getHomepageUrl(): ?string
    {
        $loggedUser = $this->security->getUser();
        if ($loggedUser instanceof Employee) {
            $homeUrl = null;
            if (!empty($loggedUser->getDefaultTabId())) {
                /** @var Tab|null $defaultTab */
                $defaultTab = $this->tabRepository->findOneBy(['id' => $loggedUser->getDefaultTabId()]);
                if (!empty($defaultTab)) {
                    if (!empty($defaultTab->getRouteName())) {
                        $homeUrl = $this->router->generate($defaultTab->getRouteName());
                    } elseif (!empty($defaultTab->getClassName())) {
                        $homeUrl = $this->legacyContext->getAdminLink($defaultTab->getClassName());
                    }
                }
            }

            if (null === $homeUrl) {
                $homeUrl = $this->legacyContext->getAdminLink('AdminDashboard');
            }

            return $homeUrl;
        }

        return null;
    }
}
