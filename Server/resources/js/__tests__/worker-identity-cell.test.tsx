import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { WorkerIdentityCell } from '@/components/ir4/worker-identity-cell';

describe('WorkerIdentityCell', () => {
    it('renders the anonymized Worker #id label from server-side stripping', () => {
        render(<WorkerIdentityCell name="Worker #42" />);

        expect(screen.getByText('Worker #42')).toBeInTheDocument();
    });

    it('renders the real worker name when identity is available', () => {
        render(<WorkerIdentityCell name="Jane Operator" />);

        expect(screen.getByText('Jane Operator')).toBeInTheDocument();
        expect(screen.queryByText(/^Worker #/)).not.toBeInTheDocument();
    });
});
