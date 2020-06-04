<?php

namespace Tim168\SearchEngineRank;

use GuzzleHttp\Exception\RequestException;
use QL\QueryList;
use Tim168\SearchEngineRank\Config\UserAgentType;
use Tim168\SearchEngineRank\Enum\SearchEngineEnum;
use Tim168\SearchEngineRank\Exceptions\InvalidArgumentException;
use Tim168\SearchEngineRank\Exceptions\SearchEngineErrorException;

class SearchEngineRank
{
    /**
     * @param $searchEngineType
     * @param $keyWord
     * @param int $currentPage
     * @param string $proxy
     * @param string $searchUrl
     * @param int $timeout
     * @return array|string
     */
    public static function getRank($searchEngineType, $keyWord, $currentPage = 1, $proxy = '', $searchUrl = '', $timeout = 5)
    {
        try {
            MatchUrlAndGetRank::verifyUrl($searchUrl,$searchEngineType);
            if (!key_exists($searchEngineType, SearchEngineEnum::ENGINE_URL)) {
                throw new InvalidArgumentException('搜索引擎类型有误');
            }
            switch ($searchEngineType) {
                case SearchEngineEnum::PC_BAI_DU:
                    $url = MatchUrlAndGetRank::getPcBaiDuUrl($keyWord, $currentPage);
                    $m = false;
                    break;
                case SearchEngineEnum::M_BAI_DU:
                    $url = MatchUrlAndGetRank::getMBaiDuUrl($keyWord, $currentPage);
                    $m = true;
                    break;
                default:
                    $url = '';
                    $m = false;
                    break;
            }
            var_dump($url);exit();
            if (!empty($url)) {
                $ql = QueryList::get($url, null,
                    [
                        'proxy' => $proxy,
                        'timeout' => $timeout,
                        'header' => [
                            "Accept-Encoding" => "gzip",
                            'User-Agent' => $m ? UserAgentType::M_UserAgent[array_rand(UserAgentType::M_UserAgent, 1)] : UserAgentType::UserAgent[array_rand(UserAgentType::UserAgent, 1)],
                            'Host' => SearchEngineEnum::HEADER_HOST[$searchEngineType],
                            'Connection' => 'keep-alive',
                            'Accept' => 'text/plain, */*',
                            'Accept-Language' => 'en-US,en;q=0.8',
                            'Content-Type' => "text/html; charset=UTF-8",
                        ]
                    ]);
                $html = $ql->getHtml();
//                var_dump($html);
                if (!empty($html)) {
                    if ($searchEngineType == SearchEngineEnum::PC_BAI_DU) {
                        if (strstr($html, '百度安全验证') || strstr($html, 'location.replace(location.href.replace("https://","http://")')) {
                            throw new SearchEngineErrorException('碰到百度安全验证，请更换代理后，再重试！');
                        }
                        $rank = MatchUrlAndGetRank::getPcBaiDuRank($html, $searchUrl, $currentPage, $proxy);
                    }
                    if ($searchEngineType == SearchEngineEnum::M_BAI_DU) {
                        if (strstr($html, '百度安全验证') || strstr($html, 'location.replace(location.href.replace("https://","http://")')) {
                            throw new SearchEngineErrorException('碰到百度安全验证，请更换代理后，再重试！');
                        }
                        $rank = MatchUrlAndGetRank::getMBaiDuRank($html, $searchUrl, $currentPage);
                    }
                    if ($searchEngineType == SearchEngineEnum::PC_360) {
                        if (strstr($html, '360搜索_访问异常出错')) {
                            throw new SearchEngineErrorException('碰到360安全验证，请更换代理后，再重试！');
                        }
                        $rank = MatchUrlAndGetRank::getPc360Rank($html, $searchUrl, $currentPage, $proxy);
                    }
                    return $rank;
                }
                return [];
            }
        } catch (InvalidArgumentException $e) {
            return $e->getMessage();
        } catch (RequestException $e) {
            return 'Http Error';
        } catch (SearchEngineErrorException $e) {
            return $e->getMessage();
        }
    }

}