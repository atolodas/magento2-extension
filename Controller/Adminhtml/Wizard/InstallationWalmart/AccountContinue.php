<?php

/*
 * @author     M2E Pro Developers Team
 * @copyright  M2E LTD
 * @license    Commercial use is forbidden
 */

namespace Ess\M2ePro\Controller\Adminhtml\Wizard\InstallationWalmart;

use Ess\M2ePro\Controller\Adminhtml\Wizard\InstallationWalmart;

/**
 * Class \Ess\M2ePro\Controller\Adminhtml\Wizard\InstallationWalmart\AccountContinue
 */
class AccountContinue extends InstallationWalmart
{
    public function execute()
    {
        $params = $this->getRequest()->getParams();
        if (empty($params)) {
            return $this->indexAction();
        }

        if (empty($params['marketplace_id'])) {
            $this->setJsonContent(['message' => $this->__('Please select Marketplace')]);
            return $this->getResult();
        }

        try {
            $accountData = [];

            $requiredFields = [
                'marketplace_id',
                'consumer_id',
                'private_key',
                'client_id',
                'client_secret'
            ];

            foreach ($requiredFields as $requiredField) {
                if (!empty($params[$requiredField])) {
                    $accountData[$requiredField] = $params[$requiredField];
                }
            }

            /** @var $marketplaceObject \Ess\M2ePro\Model\Marketplace */
            $marketplaceObject = $this->walmartFactory->getCachedObjectLoaded(
                'Marketplace',
                $params['marketplace_id']
            );

            if ($params['marketplace_id'] == \Ess\M2ePro\Helper\Component\Walmart::MARKETPLACE_CA &&
                $params['consumer_id'] && $params['private_key']) {
                $requestData = [
                    'marketplace_id' => $params['marketplace_id'],
                    'consumer_id' => $params['consumer_id'],
                    'private_key' => $params['private_key'],
                ];
            } elseif ($params['marketplace_id'] != \Ess\M2ePro\Helper\Component\Walmart::MARKETPLACE_CA &&
                $params['client_id'] && $params['client_secret']) {
                $requestData = [
                    'marketplace_id' => $params['marketplace_id'],
                    'client_id'     => $params['client_id'],
                    'client_secret' => $params['client_secret'],
                ];
            } else {
                $this->setJsonContent(['message' => $this->__('You should fill all required fields.')]);
                return $this->getResult();
            }

            $marketplaceObject->setData('status', \Ess\M2ePro\Model\Marketplace::STATUS_ENABLE)->save();

            $accountData = array_merge(
                $this->getAccountDefaultSettings(),
                [
                    'title' => "Default - {$marketplaceObject->getCode()}",
                ],
                $accountData
            );

            /** @var $model \Ess\M2ePro\Model\Account */
            $model = $this->walmartFactory->getObject('Account');
            $this->modelFactory->getObject('Walmart_Account_Builder')->build($model, $accountData);

            $id = $model->save()->getId();

            /** @var $dispatcherObject \Ess\M2ePro\Model\Walmart\Connector\Dispatcher */
            $dispatcherObject = $this->modelFactory->getObject('Walmart_Connector_Dispatcher');

            $connectorObj = $dispatcherObject->getConnector(
                'account',
                'add',
                'entityRequester',
                $requestData,
                $id
            );
            $dispatcherObject->process($connectorObj);
        } catch (\Exception $exception) {
            $this->getHelper('Module\Exception')->process($exception);

            if (!empty($model)) {
                $model->delete();
            }

            $this->modelFactory->getObject('Servicing\Dispatcher')->processTask(
                $this->modelFactory->getObject('Servicing_Task_License')->getPublicNick()
            );

            $error = 'The Walmart access obtaining is currently unavailable.<br/>Reason: %error_message%';

            if (!$this->getHelper('Module\License')->isValidDomain() ||
                !$this->getHelper('Module\License')->isValidIp()) {
                $error .= '</br>Go to the <a href="%url%" target="_blank">License Page</a>.';
                $error = $this->__(
                    $error,
                    $exception->getMessage(),
                    $this->getHelper('View\Configuration')->getLicenseUrl(['wizard' => 1])
                );
            } else {
                $error = $this->__($error, $exception->getMessage());
            }

            $this->setJsonContent(['message' => $error]);
            return $this->getResult();
        }

        $this->setStep($this->getNextStep());

        $this->setJsonContent(['result' => true]);
        return $this->getResult();
    }

    private function getAccountDefaultSettings()
    {
        $data = $this->modelFactory->getObject('Walmart_Account_Builder')->getDefaultData();

        $data['other_listings_synchronization'] = 0;
        $data['other_listings_mapping_mode'] = 0;

        $data['magento_orders_settings']['listing_other']['store_id'] = $this->getHelper('Magento\Store')
            ->getDefaultStoreId();

        return $data;
    }
}
