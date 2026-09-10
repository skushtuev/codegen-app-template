<?php

declare(strict_types=1);

namespace Common\Infra\Db\Migration;

use Yiisoft\Db\Constant\IndexType;
use Yiisoft\Db\Expression\Expression;
use Yiisoft\Db\Migration\MigrationBuilder;
use Yiisoft\Db\Migration\RevertibleMigrationInterface;

/**
 * End users. Not {{%admin_users}}, who are the admin panel staff.
 *
 * Kratos owns the credentials; this table owns app data (bans, relations, list filters).
 * Linked by `identity_id`. No foreign key: Kratos uses its own database.
 */
final class M260908000000CreateUsersTable implements RevertibleMigrationInterface
{
    public function up(MigrationBuilder $b): void
    {
        $column = $b->columnBuilder();

        $b->createTable('{{%users}}', [
            'id' => $column::uuidPrimaryKey(),
            'identity_id' => $column::uuid()->notNull(),
            'email' => $column::string(255)->notNull(),
            // Nullable: an identity may have no name (admin-created, or social sign-in).
            'name' => $column::string(255),
            'language' => $column::string(8)->notNull()->defaultValue('en'),
            'email_verified_at' => $column::timestamp(),
            'last_login_at' => $column::timestamp(),
            'banned_at' => $column::timestamp(),
            'ban_reason' => $column::string(500),
            'created_at' => $column::timestamp()->notNull()->defaultValue(new Expression('CURRENT_TIMESTAMP')),
            'updated_at' => $column::timestamp()->notNull()->defaultValue(new Expression('CURRENT_TIMESTAMP')),
        ]);

        $b->createIndex('{{%users}}', 'idx_users_identity_id', 'identity_id', IndexType::UNIQUE);
        $b->createIndex('{{%users}}', 'idx_users_email', 'email', IndexType::UNIQUE);
        $b->createIndex('{{%users}}', 'idx_users_created_at', 'created_at');
    }

    public function down(MigrationBuilder $b): void
    {
        $b->dropTable('{{%users}}');
    }
}
