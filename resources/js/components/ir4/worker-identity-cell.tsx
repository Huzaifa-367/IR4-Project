type Props = {
    name: string;
    canSeeIdentity?: boolean;
};

/** Displays real name or stable anonymized label (identity already stripped server-side). */
export function WorkerIdentityCell({ name }: Props) {
    return <span className="font-medium">{name}</span>;
}
