<?php

namespace Hail\Database\Sql;


interface SQL
{
    public const SELECT = 'SELECT',
        FROM = 'FROM',
        WHERE = 'WHERE',
        JOIN = 'JOIN',
        GROUP = 'GROUP',
        ORDER = 'ORDER',
        HAVING = 'HAVING',
        LIMIT = 'LIMIT',
        LIKE = 'LIKE',
        MATCH = 'MATCH',
        FUN = 'FUN',
        AND = 'AND',
        OR = 'OR';

    public const LOW_PRIORITY = 'LOW_PRIORITY',
        DELAYED = 'DELAYED',
        HIGH_PRIORITY = 'HIGH_PRIORITY',
        IGNORE = 'IGNORE';

    public const INSERT = 'INSERT',
        VALUES = 'VALUES',
        INSERT_LOW = [self::INSERT, self::LOW_PRIORITY],
        INSERT_DELAYED = [self::INSERT, self::DELAYED],
        INSERT_HIGH = [self::INSERT, self::HIGH_PRIORITY],
        INSERT_IGNORE = [self::INSERT, self::IGNORE],
        INSERT_LOW_IGNORE = [self::INSERT, self::LOW_PRIORITY, self::IGNORE],
        INSERT_DELAYED_IGNORE = [self::INSERT, self::DELAYED, self::IGNORE],
        INSERT_HIGH_IGNORE = [self::INSERT, self::HIGH_PRIORITY, self::IGNORE];


    public const REPLACE = 'REPLACE',
        REPLACE_LOW = [self::REPLACE, self::LOW_PRIORITY],
        REPLACE_DELAYED = [self::REPLACE, self::DELAYED];

    public const UPDATE = 'UPDATE',
        SET = 'SET';

    public const DELETE = 'DELETE';

    public const TABLE = 'TABLE',
        COLUMNS = 'COLUMNS';
}