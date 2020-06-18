<?php

namespace Drupal\openy_gc_auth_daxko_sso\Controller;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\daxko_sso\DaxkoSSOClient;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class DaxkoLinkController.
 */
class DaxkoLinkController extends ControllerBase {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Daxko Client service instance.
   *
   * @var \Drupal\daxko_sso\DaxkoSSOClient
   */
  protected $daxkoClient;

  /**
   * DaxkoLinkController constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   Config factory instance.
   * @param \Drupal\daxko_sso\DaxkoSSOClient $daxkoSSOClient
   *   Daxko client instance.
   */
  public function __construct(
    ConfigFactoryInterface $configFactory,
    DaxkoSSOClient $daxkoSSOClient) {
    $this->configFactory = $configFactory;
    $this->daxkoClient = $daxkoSSOClient;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('daxko_sso.client'),

    );
  }

  /**
   * Controller that construct's SSO link for Daxko.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Current request object.
   *
   * @return \Drupal\Core\Routing\TrustedRedirectResponse
   *   Redirect to the Daxko CRM login page.
   */
  public function getlink(Request $request) {

    if (empty($this->response)) {

      $config = $this->configFactory->get('daxko_sso.settings');
      $plugin_config = $this->configFactory->get('openy_gc_auth.provider.daxko_sso');
      $backlinkUrl = $request->getSchemeAndHttpHost() . $plugin_config->get('redirect_url');

      $daxkoSSORedirectLink = 'https://operations.daxko.com/online/auth'
        . '?response_type=code&scope=client:'
        . $config->get('client_id') . '+member:basic_info&state='
        . md5($request->getSchemeAndHttpHost())
        . '&client_id=' . $config->get('user')
        . '&redirect_uri=' . $backlinkUrl;

      $this->response = new TrustedRedirectResponse($daxkoSSORedirectLink);
      $this->response->addCacheableDependency($daxkoSSORedirectLink);

    }

    return $this->response;

  }

  /**
   * Controller that get user info based on SSO back-link data.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Current request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Json array with user fields or error.
   */
  public function backlink(Request $request) {

    $plugin_config = $this->configFactory->get('openy_gc_auth.provider.daxko_sso');

    $code = $request->get('code');

    // Check code that was generated by Open Y.
    $state = $request->get('state');
    if ($state != md5($request->getSchemeAndHttpHost())) {
      return new JsonResponse(
        [
          'error' => 1,
          'message' => 'Wrong cross site check',
        ]
      );
    }

    $backlinkUrl = $request->getSchemeAndHttpHost() . $plugin_config->get('redirect_url');
    $token = $this->daxkoClient->getUserAccessToken($code, $backlinkUrl);
    // Based on user token, get logged user data.
    $userData = $this->daxkoClient->getMyInfo($token);

    $userDetails = $this->daxkoClient->getRequest('members/' . $userData->member_id);
    // Check if this user is an active client.
    if ($userDetails->active) {
      return new JsonResponse(
        [
          'error' => 0,
          'token' => $token,
          'user' => [
            'name' => $userDetails->name->first_name,
            'email' => $userDetails->emails[0]->email,
          ],
        ]
      );
    }
    else {
      return new JsonResponse(
        [
          'error' => 1,
          'message' => 'User is not active client',
        ]
      );
    }
  }

}
