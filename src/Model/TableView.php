<?php

namespace unionco\syncdb\Model;

interface TableView
{
    public function getRows(): array;
}
