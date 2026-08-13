import { describe, expect, it } from 'vitest';
import {
    emptyPermitBoardColumns,
    replacePermitBoardColumns,
    upsertPermitBoardCard,
} from '@/lib/permit-board';
import type { PermitBoardCard } from '@/types/permit';

function card(
    patch: Partial<PermitBoardCard> & Pick<PermitBoardCard, 'uuid' | 'column'>,
): PermitBoardCard {
    return {
        id: 1,
        permit_number: 'PTW-1',
        status: 'active',
        status_label: 'Active',
        task_description: 'Task',
        valid_to: null,
        expiring: false,
        type: { id: 1, name: 'Cold Work', colour_token: 'blue' },
        zone: { id: 1, name: 'Unit A' },
        ...patch,
    };
}

describe('permit board deltas', () => {
    it('moves a card between columns and drops closed work', () => {
        const initial = emptyPermitBoardColumns();
        initial.pending_inspection = [
            card({ uuid: 'a', column: 'pending_inspection' }),
        ];

        const issued = upsertPermitBoardCard(
            initial,
            card({
                uuid: 'a',
                column: 'active',
                status: 'active',
                permit_number: 'PTW-1',
            }),
        );

        expect(issued.pending_inspection).toHaveLength(0);
        expect(issued.active).toHaveLength(1);

        const closed = upsertPermitBoardCard(
            issued,
            card({ uuid: 'a', column: 'closed', status: 'closed' }),
        );

        expect(closed.active).toHaveLength(0);
        expect(closed.pending_inspection).toHaveLength(0);
    });

    it('accepts a live snapshot envelope', () => {
        const next = replacePermitBoardColumns({
            data: {
                columns: {
                    expiring: [
                        card({
                            uuid: 'exp',
                            column: 'expiring',
                            expiring: true,
                            permit_number: 'PTW-9',
                        }),
                    ],
                },
            },
        });

        expect(next?.expiring[0]?.permit_number).toBe('PTW-9');
        expect(next?.active).toEqual([]);
    });
});
