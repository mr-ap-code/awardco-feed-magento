<?php
namespace AwardcoFeed\Module\Block;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class Feed extends Template
{
    protected $scopeConfig;
    protected $feed = [];

    public function __construct(
        Context $context,
        ScopeConfigInterface $scopeConfig,
        array $data = []
    ) {
        $this->scopeConfig = $scopeConfig;
        parent::__construct($context, $data);
    }

    public function getFeed()
    {
        if (empty($this->feed)) {
            $this->fetchFeed();
        }
        return $this->feed;
    }

    protected function fetchFeed()
    {
        $startTime = microtime(true);

        $apiKey = $this->getConfigValue('awardcofeed/general/api_key');
        $feedUrl = $this->getConfigValue('awardcofeed/general/feed_url');
        $orguserList = explode(',', $this->getConfigValue('awardcofeed/general/org_list'));
        $domain = $this->getConfigValue('awardcofeed/general/domain');
        $page = (int)$this->getConfigValue('awardcofeed/general/page');
        $limit = (int)$this->getConfigValue('awardcofeed/general/limit');
        $numPages = (int)$this->getConfigValue('awardcofeed/general/num_pages');

        // Log the values for debugging
        $this->_logger->info('API Key: ' . $apiKey);
        $this->_logger->info('Feed URL: ' . $feedUrl);
        $this->_logger->info('Org List: ' . implode(',', $orguserList));
        $this->_logger->info('Domain: ' . $domain);
        $this->_logger->info('Page: ' . $page);
        $this->_logger->info('Limit: ' . $limit);
        $this->_logger->info('Num Pages: ' . $numPages);

        $feed = [];
        $multiHandle = curl_multi_init();
        $curlHandles = [];

        for ($i = 1; $i <= $numPages; $i++) {
            $requestData = json_encode([
                'page' => $i,
                'limit' => $limit,
            ]);

            $ch = curl_init($feedUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Content-Type: application/json",
                "apiKey: $apiKey"
            ]);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $requestData);
            curl_multi_add_handle($multiHandle, $ch);
            $curlHandles[$i] = $ch;
        }

        $running = null;
        do {
            curl_multi_exec($multiHandle, $running);
            curl_multi_select($multiHandle);
        } while ($running > 0);

        foreach ($curlHandles as $i => $ch) {
            $response = curl_multi_getcontent($ch);
            $data = json_decode($response, true);

            if ($data && isset($data['success']) && $data['success']) {
                $feed = array_merge($feed, $data['recognitions'] ?? []);
            } else {
                $error = $data['message'] ?? 'Unknown error';
                $this->_logger->error('Error fetching page ' . $i . ': ' . $error);
            }

            curl_multi_remove_handle($multiHandle, $ch);
            curl_close($ch);
        }

        curl_multi_close($multiHandle);

        $feed = $this->filterFeedByUsername($feed, $orguserList, $domain);
        $feed = $this->filterOutSolrAdminInfoSystem($feed);
        
        $this->feed = $feed;
        
        $endTime = microtime(true);
        $timeConsumed = $endTime - $startTime;
        $this->_logger->info('Time consumed to load the feed: ' . $timeConsumed . ' seconds');
    }

    protected function filterFeedByUsername($feed, $orguserList, $domain)
    {
        $emailList = array_map(function($username) use ($domain) {
            return $username . '@' . $domain;
        }, $orguserList);

        return array_filter($feed, function($item) use ($emailList) {
            return in_array($item['to'][0]['email'] ?? '', $emailList);
        });
    }

    protected function filterOutSolrAdminInfoSystem($feed)
    {
        return array_filter($feed, function($item) {
            return strpos($item['request_uri'] ?? '', 'solr/admin/info/system') === false;
        });
    }

    protected function getConfigValue($path)
    {
        return $this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE);
    }
}
