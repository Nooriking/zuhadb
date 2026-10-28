import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Props = {
    baseUnit: string;
    packUnit: string;
    unitsPerPack: string;
    onChange: (field: 'base_unit_name' | 'pack_unit_name' | 'units_per_pack', value: string) => void;
    errors?: Record<string, string>;
};

export function UnitConversionFields({ baseUnit, packUnit, unitsPerPack, onChange, errors = {} }: Props) {
    const factor = Number(unitsPerPack);
    const preview =
        baseUnit.trim() && packUnit.trim() && Number.isFinite(factor) && factor > 0
            ? `1 ${packUnit.trim()} = ${unitsPerPack} ${baseUnit.trim()}`
            : null;

    return (
        <div className="space-y-3 rounded-lg border p-4">
            <div>
                <h4 className="text-sm font-semibold">Unit conversion</h4>
                <p className="text-muted-foreground text-xs">
                    Stock and sales are stored in the base unit. Use a pack unit only when you buy or sell by the box.
                </p>
            </div>
            <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                <div className="space-y-2">
                    <Label>Base unit</Label>
                    <Input
                        value={baseUnit}
                        onChange={(e) => onChange('base_unit_name', e.target.value)}
                        placeholder="bottle"
                        className={errors.base_unit_name ? 'border-destructive' : ''}
                    />
                    {errors.base_unit_name ? <p className="text-destructive text-xs">{errors.base_unit_name}</p> : null}
                </div>
                <div className="space-y-2">
                    <Label>Pack unit</Label>
                    <Input
                        value={packUnit}
                        onChange={(e) => onChange('pack_unit_name', e.target.value)}
                        placeholder="box"
                        className={errors.pack_unit_name ? 'border-destructive' : ''}
                    />
                    {errors.pack_unit_name ? <p className="text-destructive text-xs">{errors.pack_unit_name}</p> : null}
                </div>
                <div className="space-y-2">
                    <Label>Units per pack</Label>
                    <Input
                        type="number"
                        min="0.000001"
                        step="0.000001"
                        value={unitsPerPack}
                        onChange={(e) => onChange('units_per_pack', e.target.value)}
                        placeholder="12"
                        className={errors.units_per_pack ? 'border-destructive' : ''}
                    />
                    {errors.units_per_pack ? <p className="text-destructive text-xs">{errors.units_per_pack}</p> : null}
                </div>
            </div>
            {preview ? (
                <p className="rounded-md bg-muted px-3 py-2 text-sm font-medium">
                    Conversion direction: {preview}
                </p>
            ) : (
                <p className="text-muted-foreground text-xs">Leave blank if this product is always counted in a single unit.</p>
            )}
        </div>
    );
}
