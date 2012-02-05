<?php

namespace ext\activedocument;

use \CSort;

class Sort extends CSort {

    /**
     * @return string the order-by columns represented by this sort object.
     * This can be put in the ORDER BY clause of a SQL statement.
     */
    public function getOrderBy() {
        $directions = $this->getDirections();
        if (empty($directions))
            return is_string($this->defaultOrder) ? $this->defaultOrder : '';
        else {
            $orders = array();
            foreach ($directions as $attribute => $descending) {
                $definition = $this->resolveAttribute($attribute);
                if (is_array($definition)) {
                    if ($descending)
                        $orders[] = isset($definition['desc']) ? $definition['desc'] : $attribute . ' DESC';
                    else
                        $orders[] = isset($definition['asc']) ? $definition['asc'] : $attribute;
                }
                else if ($definition !== false) {
                    $attribute = $definition;
                    if ($this->modelClass !== null)
                        $attribute = $this->modelClass . '.' . $attribute;
                    $orders[] = $descending ? $attribute . ' DESC' : $attribute;
                }
            }
            return implode(', ', $orders);
        }
    }

    /**
     * Resolves the attribute label for the specified attribute.
     * This will invoke {@link Document::getAttributeLabel} to determine what label to use.
     * If the attribute refers to a virtual attribute declared in {@link attributes},
     * then the label given in the {@link attributes} will be returned instead.
     * @param string $attribute the attribute name.
     * @return string the attribute label
     */
    public function resolveLabel($attribute) {
        $definition = $this->resolveAttribute($attribute);
        if (is_array($definition)) {
            if (isset($definition['label']))
                return $definition['label'];
        }
        else if (is_string($definition))
            $attribute = $definition;
        if ($this->modelClass !== null)
            return Document::model($this->modelClass)->getAttributeLabel($attribute);
        else
            return $attribute;
    }

    /**
     * Returns the real definition of an attribute given its name.
     *
     * The resolution is based on {@link attributes} and {@link Document::attributeNames}.
     * <ul>
     * <li>When {@link attributes} is an empty array, if the name refers to an attribute of {@link modelClass},
     * then the name is returned back.</li>
     * <li>When {@link attributes} is not empty, if the name refers to an attribute declared in {@link attributes},
     * then the corresponding virtual attribute definition is returned. If {@link attributes}
     * contains a star ('*') element, the name will also be used to match against all model attributes.</li>
     * <li>In all other cases, false is returned, meaning the name does not refer to a valid attribute.</li>
     * </ul>
     * @param string $attribute the attribute name that the user requests to sort on
     * @return mixed the attribute name or the virtual attribute definition. False if the attribute cannot be sorted.
     */
    public function resolveAttribute($attribute) {
        if ($this->attributes !== array())
            $attributes = $this->attributes;
        else if ($this->modelClass !== null)
            $attributes = Document::model($this->modelClass)->attributeNames();
        else
            return false;
        foreach ($attributes as $name => $definition) {
            if (is_string($name)) {
                if ($name === $attribute)
                    return $definition;
            }
            else if ($definition === '*') {
                if ($this->modelClass !== null && Document::model($this->modelClass)->hasAttribute($attribute))
                    return $attribute;
            }
            else if ($definition === $attribute)
                return $attribute;
        }
        return false;
    }

}