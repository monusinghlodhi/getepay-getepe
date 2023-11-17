<?php
namespace Getepay\Getepe\Model\System\Message;

use Magento\Framework\Notification\MessageInterface;
use Getepay\Getepe\Model\Config;
use Requests;
use Magento\Framework\Module\ModuleList;

class UpgradeMessageNotification implements MessageInterface
{
    /**
     * Message identity
     */
    const MESSAGE_IDENTITY = 'getepay_getepe_upgrade_notification';

    public $latestVersion = '';
    public $currentVersion = '';
    public $latestVersionLink = '';

    protected $config;
    protected $moduleList;

    public function __construct(
        Config $config,
        ModuleList $moduleList
    ) {
        $this->config = $config;
        $this->moduleList = $moduleList;
    }

    /**
     * Retrieve unique system message identity
     *
     * @return string
     */
    public function getIdentity()
    {
        return self::MESSAGE_IDENTITY;
    }

    /**
     * Check whether the system message should be shown
     *
     * @return bool
     */
    public function isDisplayed()
    {
        $disableUpgradeNotice = $this->config->getConfigData(Config::DISABLE_UPGRADE_NOTICE);
        $isActive = $this->config->getConfigData(Config::KEY_ACTIVE);

        if ($isActive and !$disableUpgradeNotice)
        {
            $this->currentVersion =  $this->moduleList->getOne('Getepay_Getepe')['setup_version'];

            $request = Requests::get("https://api.github.com/repos/monusinghlodhi/getepay-getepe/releases/latest");

            if ($request->status_code === 200)
            {
                $getepayLatestRelease = json_decode($request->body);

                $this->latestVersion = $getepayLatestRelease->tag_name;
                $this->latestVersionLink = $getepayLatestRelease->html_url;
                $version_without_v = substr($this->latestVersion, 1);

                if (strpos($this->currentVersion, '-beta') !== false) {
                    $this->currentVersion = str_replace('-beta', 'beta-', $this->currentVersion);
                }

                if (version_compare($this->currentVersion, $version_without_v, '<')) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Retrieve system message text
     *
     * @return \Magento\Framework\Phrase
     */
    public function getText()
    {
        return __('Please upgrade to the latest version of Getepay (<a href="' . $this->latestVersionLink
            . '" target="_blank">' . $this->latestVersion . '</a>) ');
    }

    /**
     * Retrieve system message severity
     * Possible default system message types:
     * - MessageInterface::SEVERITY_CRITICAL
     * - MessageInterface::SEVERITY_MAJOR
     * - MessageInterface::SEVERITY_MINOR
     * - MessageInterface::SEVERITY_NOTICE
     *
     * @return int
     */
    public function getSeverity()
    {
        return self::SEVERITY_NOTICE;
    }
}
