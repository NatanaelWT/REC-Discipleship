<?php

namespace App\Services\Routing;

use Illuminate\Support\Facades\Route;

class AppPageRouteMap
{
    /**
     * @var array<string, string>
     */
    private const ROUTES = [
        'public_links' => 'home',
        'public_dg_branch' => 'public.dg.branch',
        'public_dg_report' => 'public.dg.report.redirect',
        'public_member_feedback_branch' => 'public.member-feedback.branch',
        'public_member_feedback' => 'public.member-feedback.form',
        'public_materials' => 'materials.index',
        'public_material_preview' => 'materials.preview.redirect',
        'public_material_download' => 'materials.download.redirect',
        'public_difficult_question_submit' => 'public.difficult-question.submit',
        'public_difficult_answer_lookup' => 'public.difficult-question.answer',
        'public_menu_empty' => 'public.menu-empty',
        'login' => 'auth.login',
        'settings' => 'settings',
        'developer_dashboard' => 'developer.dashboard',
        'developer_branches' => 'developer.branches',
        'developer_users' => 'developer.users',
        'developer_config' => 'developer.config',
        'developer_statistics' => 'developer.statistics',
        'discipleship_dashboard' => 'discipleship.dashboard',
        'groups_list' => 'discipleship.groups',
        'people' => 'discipleship.people',
        'people_list' => 'discipleship.people-list',
        'people_tree' => 'discipleship.tree',
        'people_tree_v2' => 'discipleship.tree-v2',
        'spiritual_journey' => 'discipleship.spiritual-journey',
        'dg_reports_recap' => 'discipleship.reports-recap',
        'member_feedback_recap' => 'discipleship.member-feedback-recap',
        'msk_classes' => 'discipleship.msk-classes',
        'discipleship_targets' => 'discipleship.targets',
        'difficult_questions_admin' => 'discipleship.difficult-questions',
        'worship_penatalayan' => 'worship.penatalayan',
        'worship_penatalayan_image' => 'worship.penatalayan.image',
        'secure_file' => 'secure-file.show',
    ];

    /**
     * @return array<string, string>
     */
    public static function pages(): array
    {
        return self::ROUTES;
    }

    public static function hasPage(string $page): bool
    {
        return array_key_exists(trim($page), self::ROUTES);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public static function pageUrl(string $page, array $params = []): string
    {
        unset($params['page']);

        $routeName = self::ROUTES[trim($page)] ?? self::ROUTES['public_links'];
        if (! Route::has($routeName)) {
            return '/';
        }

        return route($routeName, $params, false);
    }
}
