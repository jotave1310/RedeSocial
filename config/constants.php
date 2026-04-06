<?php
declare(strict_types=1);

const APP_NAME = 'CARVASILVA';
const APP_TIMEZONE = 'America/Sao_Paulo';
const CURRENCY_SYMBOL = "\u{20A2}";

const INITIAL_CREDITS = 1000;

const USERNAME_REGEX = '/^[A-Za-zÀ-ú]{2,30}_[1-9][0-9]?[A-Ca-c]$/u';
const PASSWORD_MIN_LENGTH = 8;

const POST_MAX_LENGTH = 280;
const COMMENT_MAX_LENGTH = 500;
const BIO_MAX_LENGTH = 160;

const MILESTONES = [1000, 5000, 10000, 50000, 100000];

const CREDIT_RULES = [
    'signup_bonus' => 1000,
    'daily_login_bonus' => 50,
    'post_bonus' => 10,
    'post_bonus_daily_limit' => 10,
    'like_received_bonus' => 2,
    'minimum_tip' => 5,
    'bet_platform_fee_percent' => 5,
    'daily_share_bonus' => 30,
    'referral_bonus' => 250,
    'weekly_streak_bonus' => 120,
];

const RATE_LIMITS = [
    'posts_per_hour' => 10,
    'actions_per_minute' => 20,
    'anonymous_post_interval_seconds' => 3600,
];

const BADGE_KEYS = [
    'estrela_escola' => 'Estrela da Escola',
    'diamante' => 'Diamante',
    'apostador' => 'Apostador',
    'influencer' => 'Influencer',
    'em_chamas' => 'Em Chamas',
    'anonimo_misterioso' => 'Anonimo Misterioso',
];

const REWARD_PROGRAM = [
    [
        'key' => 'signup',
        'label' => 'Criar conta',
        'reward' => 1000,
        'frequency' => 'unico',
        'icon' => 'fa-user-plus',
    ],
    [
        'key' => 'referral',
        'label' => 'Convidar amigo',
        'reward' => 250,
        'frequency' => 'por convite valido',
        'icon' => 'fa-user-group',
    ],
    [
        'key' => 'daily_post',
        'label' => 'Post comum',
        'reward' => 10,
        'frequency' => 'ate 10x por dia',
        'icon' => 'fa-pen',
    ],
    [
        'key' => 'daily_share',
        'label' => 'Compartilhar destaque',
        'reward' => 30,
        'frequency' => '1x por dia',
        'icon' => 'fa-share-nodes',
    ],
    [
        'key' => 'received_like',
        'label' => 'Like recebido',
        'reward' => 2,
        'frequency' => 'por like valido',
        'icon' => 'fa-heart',
    ],
    [
        'key' => 'weekly_streak',
        'label' => 'Sequencia semanal',
        'reward' => 120,
        'frequency' => '1x por semana',
        'icon' => 'fa-bolt',
    ],
];

date_default_timezone_set(APP_TIMEZONE);
