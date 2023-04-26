<?php


namespace Cubify\Cubes;


use Cubify\Exceptions\CubifyException;

trait Sql
{
    /**
     * Build mysql query from template and passed variables.
     * Uses sql template files in resources/sql with mysql prefix.
     *
     * @param string $template Template name without prefix (mysql_ or so)
     * @param array $vars Variables in name => value format.
     * @return string $query Executable sql query.
     * @throws CubifyException
     */
    public function buildQuery($template, $vars)
    {
        $queryTemplate = $this->getSqlTemplate('mysql_' . $template);
        if (is_array($vars)) {
            $varNames = array_map(function ($value) {
                return ':' . $value;
            }, array_keys($vars));
            $query = str_replace($varNames, array_values($vars), $queryTemplate);
        } else {
            throw new CubifyException(sprintf('Wrong variable definition for sql template %s', $template));
        }

        return $query;
    }

    /**
     * Look for sql template file and return its contents.
     *
     * @param string $name Template filename including suffix (mysql_ or other)
     * @return false|string
     */
    public function getSqlTemplate($name)
    {
        $template = '';
        try {
            $name = strpos($name, '.sql' !== false) ? $name : $name . '.sql';
            $path = CUBIFY_SQL_PATH . DS . $name;
            if (file_exists($path)) {
                $template = file_get_contents($path);
                if ($template) {
                    $template = trim(trim($template),';');
                }
                else {
                    throw new CubifyException(sprintf('Can not read template file %s or the file is empty', $path));
                }
            } else {
                throw new CubifyException(sprintf('Sql template file %s not found', $path));
            }
        } catch (CubifyException $e) {
            echo $e->getMessage();
        }
        return $template;
    }
}