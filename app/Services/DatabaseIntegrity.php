<?php

namespace App\Services;

class DatabaseIntegrity
{
    private function ident(string $s): string
    {
        return '`'.str_replace('`', '``', $s).'`';
    }

    public function assertValid($db): array
    {
        $schema = $db->selectOne('SELECT DATABASE() AS name,@@foreign_key_checks AS fk,@@check_constraint_checks AS ck');
        if ((int) $schema->fk !== 1 || (int) $schema->ck !== 1) {
            throw new \RuntimeException('Las restricciones SQL deben estar habilitadas.');
        }
        $result = ['foreign_keys' => 0, 'checks' => 0, 'unique_indexes' => 0, 'orphans' => 0, 'check_violations' => 0, 'duplicate_groups' => 0];
        $groups = [];
        foreach ($db->select('SELECT TABLE_NAME,CONSTRAINT_NAME,COLUMN_NAME,REFERENCED_TABLE_SCHEMA,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE CONSTRAINT_SCHEMA=DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL ORDER BY TABLE_NAME,CONSTRAINT_NAME,ORDINAL_POSITION') as $k) {
            $groups[$k->TABLE_NAME.'.'.$k->CONSTRAINT_NAME][] = $k;
        }
        foreach ($groups as $parts) {
            $first = $parts[0];
            if ($first->REFERENCED_TABLE_SCHEMA !== $schema->name) {
                throw new \RuntimeException('Se rechazó una referencia entre esquemas.');
            }$on = [];
            $nn = [];
            foreach ($parts as $k) {
                $on[] = 'c.'.$this->ident($k->COLUMN_NAME).'=p.'.$this->ident($k->REFERENCED_COLUMN_NAME);
                $nn[] = 'c.'.$this->ident($k->COLUMN_NAME).' IS NOT NULL';
            }$result['foreign_keys']++;
            $result['orphans'] += (int) $db->selectOne('SELECT COUNT(*) AS n FROM '.$this->ident($first->TABLE_NAME).' c LEFT JOIN '.$this->ident($first->REFERENCED_TABLE_NAME).' p ON '.implode(' AND ', $on).' WHERE '.implode(' AND ', $nn).' AND p.'.$this->ident($first->REFERENCED_COLUMN_NAME).' IS NULL')->n;
        }
        foreach ($db->select('SELECT TABLE_NAME,CHECK_CLAUSE FROM information_schema.CHECK_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE()') as $c) {
            $result['checks']++;
            $result['check_violations'] += (int) $db->selectOne('SELECT COUNT(*) AS n FROM '.$this->ident($c->TABLE_NAME).' WHERE NOT ('.$c->CHECK_CLAUSE.')')->n;
        }
        $groups = [];
        foreach ($db->select('SELECT TABLE_NAME,INDEX_NAME,COLUMN_NAME,SUB_PART FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND NON_UNIQUE=0 ORDER BY TABLE_NAME,INDEX_NAME,SEQ_IN_INDEX') as $i) {
            $groups[$i->TABLE_NAME.'.'.$i->INDEX_NAME][] = $i;
        }
        foreach ($groups as $parts) {
            $keys = [];
            $nn = [];
            foreach ($parts as $i) {
                $column = $this->ident($i->COLUMN_NAME);
                $keys[] = $i->SUB_PART ? 'LEFT('.$column.','.(int) $i->SUB_PART.')' : $column;
                $nn[] = $column.' IS NOT NULL';
            }$result['unique_indexes']++;
            $result['duplicate_groups'] += (int) $db->selectOne('SELECT COUNT(*) AS n FROM (SELECT '.implode(',', $keys).' FROM '.$this->ident($parts[0]->TABLE_NAME).' WHERE '.implode(' AND ', $nn).' GROUP BY '.implode(',', $keys).' HAVING COUNT(*)>1) d')->n;
        }
        if ($result['orphans'] || $result['check_violations'] || $result['duplicate_groups']) {
            throw new \RuntimeException('La integridad SQL no coincide con las restricciones.');
        }

        return $result;
    }
}
