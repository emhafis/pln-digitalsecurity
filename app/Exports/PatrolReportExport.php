<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class PatrolReportExport implements Export, WithMultipleSheets
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(private array $data) {}

    public function sheets(): array
    {
        return [
            new PatrolReportSummarySheet($this->data),
            new PatrolReportUnitSheet($this->data),
            new PatrolReportPostSheet($this->data),
            new PatrolReportTeamSheet($this->data),
            new PatrolReportSecuritySheet($this->data),
            new PatrolReportIncidentSheet($this->data),
        ];
    }
}
