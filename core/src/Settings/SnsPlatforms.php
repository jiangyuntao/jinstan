<?php

declare(strict_types=1);

namespace Jinstan\Core\Settings;

final class SnsPlatforms
{
    public const GROUPS = [
        'global' => [
            'facebook' => 'Facebook',
            'instagram' => 'Instagram',
            'x' => 'X',
            'youtube' => 'YouTube',
            'tiktok' => 'TikTok',
            'linkedin' => 'LinkedIn',
            'threads' => 'Threads',
            'bluesky' => 'Bluesky',
            'snapchat' => 'Snapchat',
            'tumblr' => 'Tumblr',
            'pinterest' => 'Pinterest',
            'whatsapp' => 'WhatsApp',
            'telegram' => 'Telegram',
            'line' => 'LINE',
            'discord' => 'Discord',
            'github' => 'GitHub',
            'reddit' => 'Reddit',
            'medium' => 'Medium',
            'mastodon' => 'Mastodon',
            'twitch' => 'Twitch',
            'vimeo' => 'Vimeo',
        ],
        'china' => [
            'wechat' => '微信',
            'wechat_channels' => '微信视频号',
            'qq' => 'QQ',
            'qzone' => 'QQ 空间',
            'weibo' => '微博',
            'douyin' => '抖音',
            'xiaohongshu' => '小红书',
            'bilibili' => '哔哩哔哩',
            'zhihu' => '知乎',
            'kuaishou' => '快手',
            'douban' => '豆瓣',
            'toutiao' => '今日头条',
        ],
        'korea' => [
            'kakaotalk' => 'KakaoTalk',
            'kakaostory' => 'KakaoStory',
            'kakao_channel' => 'Kakao Channel',
            'naver_blog' => 'Naver Blog',
            'naver_cafe' => 'Naver Cafe',
            'naver_band' => 'Naver BAND',
            'naver_tv' => 'Naver TV',
            'soop' => 'SOOP',
        ],
    ];

    public static function isValidUrl(string $url): bool
    {
        $scheme = parse_url($url, PHP_URL_SCHEME);

        return in_array($scheme, ['http', 'https'], true) && filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    public static function icon(string $handle): string
    {
        return match ($handle) {
            'facebook' => 'ti-brand-facebook',
            'instagram' => 'ti-brand-instagram',
            'x' => 'ti-brand-x',
            'youtube' => 'ti-brand-youtube',
            'tiktok' => 'ti-brand-tiktok',
            'linkedin' => 'ti-brand-linkedin',
            'threads' => 'ti-brand-threads',
            'bluesky' => 'ti-brand-bluesky',
            'snapchat' => 'ti-brand-snapchat',
            'tumblr' => 'ti-brand-tumblr',
            'pinterest' => 'ti-brand-pinterest',
            'whatsapp' => 'ti-brand-whatsapp',
            'telegram' => 'ti-brand-telegram',
            'line' => 'ti-brand-line',
            'discord' => 'ti-brand-discord',
            'github' => 'ti-brand-github',
            'reddit' => 'ti-brand-reddit',
            'medium' => 'ti-brand-medium',
            'mastodon' => 'ti-brand-mastodon',
            'twitch' => 'ti-brand-twitch',
            'vimeo' => 'ti-brand-vimeo',
            'weibo' => 'ti-brand-weibo',
            'bilibili' => 'ti-brand-bilibili',
            default => 'ti-world',
        };
    }
}
