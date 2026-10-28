import fs from 'fs';

const create = fs.readFileSync('resources/js/pages/products/create.tsx', 'utf8');
const edit = fs.readFileSync('resources/js/pages/products/edit.tsx', 'utf8');

const result = {
    componentExists: fs.existsSync('resources/js/components/unit-conversion-fields.tsx'),
    createImport: create.includes("from '@/components/unit-conversion-fields'"),
    createUsage: /<UnitConversionFields\b/.test(create),
    editImport: edit.includes("from '@/components/unit-conversion-fields'"),
    editUsage: /<UnitConversionFields\b/.test(edit),
    createFormFields: ["base_unit_name: ''", "pack_unit_name: ''", "units_per_pack: ''"].every((s) =>
        create.includes(s),
    ),
    createAppends: ['base_unit_name', 'pack_unit_name', 'units_per_pack'].every((f) =>
        create.includes(`formData.append('${f}'`),
    ),
};

console.log(JSON.stringify(result, null, 2));

if (!result.componentExists || !result.createImport || !result.createUsage || !result.editImport || !result.editUsage) {
    process.exit(1);
}
