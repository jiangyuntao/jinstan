<?php

declare(strict_types=1);

namespace Jinstan\Core\Database\Migrations;

use Jinstan\Core\Database\Migration;
use Jinstan\Core\Database\Schema\Schema;
use Jinstan\Core\Infrastructure\Database\Connection;

final class Version202610030001 extends Migration
{
    public function getDescription(): string
    {
        return '创建 Jinstan Core 基线数据结构。';
    }

    public function up(Schema $schema): void
    {
        $users = $schema->createTable('users');
        $users->addColumn('id', 'integer', ['autoincrement' => true]);
        $users->addColumn('email', 'string', ['length' => 190]);
        $users->addColumn('password_hash', 'string', ['length' => 255]);
        $users->addColumn('created_at', 'datetime_immutable');
        $users->addColumn('updated_at', 'datetime_immutable');
        $users->setPrimaryKey(['id']);
        $users->addUniqueIndex(['email'], 'users_email_unique');

        $settings = $schema->createTable('settings');
        $settings->addColumn('setting_key', 'string', ['length' => 190]);
        $settings->addColumn('setting_value', 'text');
        $settings->setPrimaryKey(['setting_key']);

        $roles = $schema->createTable('roles');
        $roles->addColumn('role_key', 'string', ['length' => 100]);
        $roles->addColumn('label', 'string', ['length' => 100]);
        $roles->setPrimaryKey(['role_key']);

        $permissions = $schema->createTable('permissions');
        $permissions->addColumn('permission_key', 'string', ['length' => 100]);
        $permissions->addColumn('label', 'string', ['length' => 100]);
        $permissions->setPrimaryKey(['permission_key']);

        $rolePermissions = $schema->createTable('role_permissions');
        $rolePermissions->addColumn('role_key', 'string', ['length' => 100]);
        $rolePermissions->addColumn('permission_key', 'string', ['length' => 100]);
        $rolePermissions->setPrimaryKey(['role_key', 'permission_key']);
        $rolePermissions->addForeignKeyConstraint('roles', ['role_key'], ['role_key'], ['onDelete' => 'CASCADE']);
        $rolePermissions->addForeignKeyConstraint('permissions', ['permission_key'], ['permission_key'], ['onDelete' => 'CASCADE']);

        $userRoles = $schema->createTable('user_roles');
        $userRoles->addColumn('user_id', 'integer');
        $userRoles->addColumn('role_key', 'string', ['length' => 100]);
        $userRoles->setPrimaryKey(['user_id', 'role_key']);
        $userRoles->addForeignKeyConstraint('users', ['user_id'], ['id'], ['onDelete' => 'CASCADE']);
        $userRoles->addForeignKeyConstraint('roles', ['role_key'], ['role_key'], ['onDelete' => 'CASCADE']);

        $contents = $schema->createTable('contents');
        $contents->addColumn('id', 'integer', ['autoincrement' => true]);
        $contents->addColumn('type', 'string', ['length' => 100]);
        $contents->addColumn('parent_id', 'integer', ['notnull' => false]);
        $contents->addColumn('status', 'string', ['length' => 30]);
        $contents->addColumn('template', 'string', ['length' => 100, 'notnull' => false]);
        $contents->addColumn('author_id', 'integer', ['notnull' => false]);
        $contents->addColumn('published_at', 'datetime_immutable', ['notnull' => false]);
        $contents->addColumn('data', 'text');
        $contents->addColumn('created_at', 'datetime_immutable');
        $contents->addColumn('updated_at', 'datetime_immutable');
        $contents->setPrimaryKey(['id']);
        $contents->addIndex(['type', 'status'], 'contents_type_status');
        $contents->addForeignKeyConstraint('users', ['author_id'], ['id'], ['onDelete' => 'SET NULL']);

        $translations = $schema->createTable('content_translations');
        $translations->addColumn('id', 'integer', ['autoincrement' => true]);
        $translations->addColumn('content_id', 'integer');
        $translations->addColumn('locale', 'string', ['length' => 20]);
        $translations->addColumn('title', 'string', ['length' => 255]);
        $translations->addColumn('slug', 'string', ['length' => 190]);
        $translations->addColumn('excerpt', 'text', ['notnull' => false]);
        $translations->addColumn('data', 'text');
        $translations->addColumn('seo_title', 'string', ['length' => 255, 'notnull' => false]);
        $translations->addColumn('meta_description', 'text', ['notnull' => false]);
        $translations->addColumn('updated_at', 'datetime_immutable');
        $translations->setPrimaryKey(['id']);
        $translations->addUniqueIndex(['content_id', 'locale'], 'content_locale_unique');
        $translations->addUniqueIndex(['locale', 'slug'], 'content_locale_slug_unique');
        $translations->addForeignKeyConstraint('contents', ['content_id'], ['id'], ['onDelete' => 'CASCADE']);

        $relations = $schema->createTable('content_relations');
        $relations->addColumn('source_content_id', 'integer');
        $relations->addColumn('relation_name', 'string', ['length' => 100]);
        $relations->addColumn('target_content_id', 'integer');
        $relations->addColumn('position', 'integer', ['default' => 0]);
        $relations->addColumn('meta', 'text');
        $relations->setPrimaryKey(['source_content_id', 'relation_name', 'target_content_id']);
        $relations->addForeignKeyConstraint('contents', ['source_content_id'], ['id'], ['onDelete' => 'CASCADE']);
        $relations->addForeignKeyConstraint('contents', ['target_content_id'], ['id'], ['onDelete' => 'CASCADE']);

        $plugins = $schema->createTable('plugins');
        $plugins->addColumn('name', 'string', ['length' => 100]);
        $plugins->addColumn('version', 'string', ['length' => 30]);
        $plugins->addColumn('enabled', 'boolean');
        $plugins->setPrimaryKey(['name']);

        $taxonomies = $schema->createTable('taxonomies');
        $taxonomies->addColumn('handle', 'string', ['length' => 100]);
        $taxonomies->addColumn('label', 'string', ['length' => 100]);
        $taxonomies->setPrimaryKey(['handle']);

        $terms = $schema->createTable('terms');
        $terms->addColumn('id', 'integer', ['autoincrement' => true]);
        $terms->addColumn('taxonomy_handle', 'string', ['length' => 100]);
        $terms->addColumn('parent_id', 'integer', ['notnull' => false]);
        $terms->setPrimaryKey(['id']);
        $terms->addForeignKeyConstraint('taxonomies', ['taxonomy_handle'], ['handle'], ['onDelete' => 'CASCADE']);

        $termTranslations = $schema->createTable('term_translations');
        $termTranslations->addColumn('term_id', 'integer');
        $termTranslations->addColumn('locale', 'string', ['length' => 20]);
        $termTranslations->addColumn('name', 'string', ['length' => 190]);
        $termTranslations->addColumn('slug', 'string', ['length' => 190]);
        $termTranslations->setPrimaryKey(['term_id', 'locale']);
        $termTranslations->addUniqueIndex(['locale', 'slug'], 'term_locale_slug_unique');
        $termTranslations->addForeignKeyConstraint('terms', ['term_id'], ['id'], ['onDelete' => 'CASCADE']);

        $contentTerms = $schema->createTable('content_terms');
        $contentTerms->addColumn('content_id', 'integer');
        $contentTerms->addColumn('term_id', 'integer');
        $contentTerms->setPrimaryKey(['content_id', 'term_id']);
        $contentTerms->addForeignKeyConstraint('contents', ['content_id'], ['id'], ['onDelete' => 'CASCADE']);
        $contentTerms->addForeignKeyConstraint('terms', ['term_id'], ['id'], ['onDelete' => 'CASCADE']);

        $termPositions = $schema->createTable('taxonomy_term_positions');
        $termPositions->addColumn('term_id', 'integer');
        $termPositions->addColumn('position', 'integer');
        $termPositions->setPrimaryKey(['term_id']);
        $termPositions->addIndex(['position'], 'taxonomy_term_positions_position');
        $termPositions->addForeignKeyConstraint('terms', ['term_id'], ['id'], ['onDelete' => 'CASCADE']);

        $redirects = $schema->createTable('redirects');
        $redirects->addColumn('source_path', 'string', ['length' => 255]);
        $redirects->addColumn('target_path', 'string', ['length' => 255]);
        $redirects->addColumn('status_code', 'integer');
        $redirects->setPrimaryKey(['source_path']);

        $menus = $schema->createTable('navigation_menus');
        $menus->addColumn('id', 'integer', ['autoincrement' => true]);
        $menus->addColumn('handle', 'string', ['length' => 100]);
        $menus->addColumn('name', 'string', ['length' => 100]);
        $menus->addColumn('created_at', 'datetime_immutable');
        $menus->addColumn('updated_at', 'datetime_immutable');
        $menus->setPrimaryKey(['id']);
        $menus->addUniqueIndex(['handle'], 'navigation_menus_handle_unique');

        $menuItems = $schema->createTable('navigation_menu_items');
        $menuItems->addColumn('id', 'integer', ['autoincrement' => true]);
        $menuItems->addColumn('menu_id', 'integer');
        $menuItems->addColumn('parent_id', 'integer', ['notnull' => false]);
        $menuItems->addColumn('position', 'integer', ['default' => 0]);
        $menuItems->setPrimaryKey(['id']);
        $menuItems->addIndex(['menu_id', 'position'], 'navigation_menu_items_position');
        $menuItems->addIndex(['parent_id', 'position'], 'navigation_menu_items_parent_position');
        $menuItems->addForeignKeyConstraint('navigation_menus', ['menu_id'], ['id'], ['onDelete' => 'CASCADE']);
        $menuItems->addForeignKeyConstraint('navigation_menu_items', ['parent_id'], ['id'], ['onDelete' => 'SET NULL']);

        $menuTranslations = $schema->createTable('navigation_menu_item_translations');
        $menuTranslations->addColumn('item_id', 'integer');
        $menuTranslations->addColumn('locale', 'string', ['length' => 20]);
        $menuTranslations->addColumn('label', 'string', ['length' => 190]);
        $menuTranslations->addColumn('url', 'string', ['length' => 2048]);
        $menuTranslations->setPrimaryKey(['item_id', 'locale']);
        $menuTranslations->addForeignKeyConstraint('navigation_menu_items', ['item_id'], ['id'], ['onDelete' => 'CASCADE']);

        $menuTargets = $schema->createTable('navigation_menu_item_targets');
        $menuTargets->addColumn('item_id', 'integer');
        $menuTargets->addColumn('target', 'string', ['length' => 20, 'default' => '_self']);
        $menuTargets->setPrimaryKey(['item_id']);
        $menuTargets->addForeignKeyConstraint('navigation_menu_items', ['item_id'], ['id'], ['onDelete' => 'CASCADE']);
    }

    public function upData(Connection $connection): void
    {
        $connection->insert('taxonomies', ['handle' => 'category', 'label' => '分类']);
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('navigation_menu_item_targets');
        $schema->dropTable('navigation_menu_item_translations');
        $schema->dropTable('navigation_menu_items');
        $schema->dropTable('navigation_menus');
        $schema->dropTable('redirects');
        $schema->dropTable('taxonomy_term_positions');
        $schema->dropTable('content_terms');
        $schema->dropTable('term_translations');
        $schema->dropTable('terms');
        $schema->dropTable('taxonomies');
        $schema->dropTable('plugins');
        $schema->dropTable('content_relations');
        $schema->dropTable('content_translations');
        $schema->dropTable('contents');
        $schema->dropTable('user_roles');
        $schema->dropTable('role_permissions');
        $schema->dropTable('permissions');
        $schema->dropTable('roles');
        $schema->dropTable('settings');
        $schema->dropTable('users');
    }
}
