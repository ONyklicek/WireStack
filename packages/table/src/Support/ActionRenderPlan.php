<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Support;

use NyonCode\WireCore\Actions\ActionGroup;
use NyonCode\WireTable\Actions\HeaderActionClickResolver;
use NyonCode\WireTable\Actions\TableActionClickResolver;
use NyonCode\WireTable\Table;

/**
 * Which actions one render offers, and how they are wired to the host.
 *
 * Part of {@see TableRenderPlan}. Four surfaces sharing one vocabulary, which is
 * why they resolve together rather than wherever each is drawn:
 *
 *  - **row** actions, in the actions column;
 *  - **bulk** actions, over the selection;
 *  - **header** actions, in the toolbar;
 *  - **mobile** row actions, in the stacked card — a separate list on purpose,
 *    because a finger has no double-click, no right-click and no Delete key, so a
 *    behaviour-only record action has to render there as an ordinary button.
 *
 * The two `*ActionGroup` entries are the collapsed forms: mobile cards and the
 * toolbar can each fold their actions into one dropdown. Both halves sit in the
 * document at every width and CSS decides which is shown, which is why the
 * collapsed copy is built shortcut-less.
 *
 * The **click resolvers** are the seam that keeps `wire-core`'s action views host
 * agnostic: they are the single place that maps an action to this table's
 * `executeTableAction` / `openActionModal`.
 */
final class ActionRenderPlan
{
    /**
     * @param  array<int, mixed>  $row  Row actions, with the configured
     *                                  solid/quiet style already applied.
     * @param  array<int, mixed>  $bulk
     * @param  array<int, mixed>  $header
     * @param  array<int, mixed>  $mobile
     * @param  string  $position  'start' or 'end'.
     * @param  string  $alignment  'left', 'center' or 'right'.
     * @param  string  $alignmentClass  A literal `text-*` utility.
     * @param  string  $justifyClass  A literal `justify-*` utility.
     */
    private function __construct(
        public readonly array $row,
        public readonly bool $hasAny,
        public readonly array $bulk,
        public readonly bool $hasBulk,
        public readonly array $header,
        public readonly bool $hasHeader,
        public readonly array $mobile,
        public readonly bool $hasMobile,
        public readonly bool $collapseMobile,
        public readonly ?ActionGroup $mobileGroup,
        public readonly bool $collapseHeader,
        public readonly ?ActionGroup $mobileHeaderGroup,
        public readonly ?HeaderActionClickResolver $headerClick,
        public readonly TableActionClickResolver $click,
        public readonly string $position,
        public readonly string $alignment,
        public readonly string $alignmentClass,
        public readonly string $justifyClass,
        public readonly string $columnLabel,
        public readonly ?string $columnWidth,
    ) {}

    public static function resolve(Table $table): self
    {
        $bulk = $table->getBulkActions();
        $header = $table->getHeaderActions();
        $mobile = $table->getMobileRowActionsForDisplay();

        $collapseMobile = $table->shouldCollapseActionsOnMobile();
        $collapseHeader = $table->shouldCollapseHeaderActionsOnMobile();

        return new self(
            row: $table->getRowActionsForDisplay(),
            hasAny: $table->hasActions(),
            bulk: $bulk,
            hasBulk: ! empty($bulk),
            header: $header,
            hasHeader: ! empty($header),
            mobile: $mobile,
            hasMobile: $mobile !== [],
            collapseMobile: $collapseMobile,
            mobileGroup: $collapseMobile ? $table->getMobileActionGroup() : null,
            collapseHeader: $collapseHeader,
            mobileHeaderGroup: $collapseHeader ? $table->getMobileHeaderActionGroup() : null,
            headerClick: $collapseHeader ? new HeaderActionClickResolver : null,
            click: new TableActionClickResolver,
            position: $table->getActionsPosition(),
            alignment: $table->getActionsAlignment(),
            alignmentClass: $table->getActionsAlignmentClass(),
            justifyClass: $table->getActionsJustifyClass(),
            columnLabel: $table->getActionsColumnLabel() ?? __('wire-table::messages.actions_label'),
            columnWidth: $table->getActionsColumnWidth(),
        );
    }
}
