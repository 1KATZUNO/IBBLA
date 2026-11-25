<?php

namespace App\Services\Promesas\Importacion;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * Plantilla del Excel de promesas futuras: hoja «Promesas» con la fila de titulos (Miembro, Clase, Frecuencia
 * con su lista Semanal / Quincenal / Mensual, y las categorias de promesa activas de la iglesia en su orden) y
 * hoja «Instrucciones».
 */
class PlantillaPromesas
{
    /** @param  list<string>  $categorias */
    public function libro(array $categorias): Spreadsheet
    {
        $libro = new Spreadsheet;
        $hoja = $libro->getActiveSheet();
        $hoja->setTitle('Promesas');

        $titulos = array_merge(['Miembro', 'Clase', 'Frecuencia'], $categorias);
        foreach ($titulos as $i => $titulo) {
            $col = Coordinate::stringFromColumnIndex($i + 1);
            $hoja->setCellValue($col.'1', $titulo);
            $hoja->getColumnDimension($col)->setWidth($i === 0 ? 36 : ($i === 1 ? 18 : 16));
            if ($i >= 3) {
                $hoja->getStyle($col.'2:'.$col.'2001')->getNumberFormat()->setFormatCode('#,##0.00');
            }
        }
        $ultima = Coordinate::stringFromColumnIndex(count($titulos));
        $hoja->getStyle('A1:'.$ultima.'1')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $hoja->getStyle('A1:'.$ultima.'1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1F3A5F');
        $hoja->freezePane('D2');

        // Lista desplegable de la columna Frecuencia
        $lista = new DataValidation;
        $lista->setType(DataValidation::TYPE_LIST)
            ->setErrorStyle(DataValidation::STYLE_STOP)
            ->setAllowBlank(true)
            ->setShowDropDown(true)
            ->setShowErrorMessage(true)
            ->setErrorTitle('Frecuencia')
            ->setError('Elige Semanal, Quincenal o Mensual (o déjala vacía).')
            ->setShowInputMessage(true)
            ->setPromptTitle('Frecuencia')
            ->setPrompt('Semanal, Quincenal o Mensual. Vacía = la que elijas al subir el archivo.')
            ->setFormula1('"Semanal,Quincenal,Mensual"');
        $lista->setSqref('C2:C2001');
        $hoja->setDataValidation('C2:C2001', $lista);

        $ayuda = $libro->createSheet();
        $ayuda->setTitle('Instrucciones');
        $ayuda->getColumnDimension('A')->setWidth(110);
        $lineas = [
            'Cómo llenar el Excel de promesas futuras',
            '',
            '1. Usa la primera hoja («Promesas»). La fila 1 es solo para los títulos: no la borres.',
            '2. Columna A: Miembro (o Nombre). Escribe el nombre de la persona como está en el sistema, una persona por fila.',
            '3. Columna B: Clase. Es opcional; solo se usa si la persona es nueva y existe una clase con ese nombre.',
            '4. Columna C: Frecuencia. Elige de la lista Semanal, Quincenal o Mensual: vale para todos los montos de esa persona.',
            '   Si la dejas vacía, se usa la frecuencia que elijas al subir el archivo (por defecto, semanal).',
            '   Cualquier otro texto deja la fila marcada con error en la revisión: corrígela o la omites.',
            '   Un archivo sin la columna Frecuencia también sirve: entonces todas las filas llevan la que elijas al subirlo.',
            '5. De la columna D en adelante: una columna por categoría, con su nombre en la fila 1 (Misiones, Seminario…).',
            '6. En cada celda va el monto que la persona promete en esa categoría. Vacío o 0 = sin promesa en esa categoría.',
            '   Escribe solo el número (5000 o 2500.50), sin el símbolo de colones. Se guardan con dos decimales.',
            '7. Si pones en la fila 1 una categoría que la iglesia no tiene, el sistema te propone crearla como categoría nueva.',
            '   La categoría nueva no aparece en los sobres ni en los reportes hasta el día en que el corte entra en vigor.',
            '8. La moneda se elige al subir el archivo y vale para todos los montos.',
            '9. Antes de guardar nada, el sistema te muestra una revisión: qué personas calzan, cuáles tienen un nombre parecido',
            '   y cuáles no existen. Ahí decides. Nada se guarda hasta que tocas «Aplicar».',
            '10. Si una persona ya está en el corte, no se cambia ni se duplica: se deja lo que ya tenía. Para cambiarla, edítala a mano.',
            '   Por eso puedes volver a subir el mismo archivo sin miedo: no agrega nada repetido.',
            '11. Máximo 2 MB y 2.000 personas por archivo.',
        ];
        foreach ($lineas as $i => $linea) {
            $ayuda->setCellValue('A'.($i + 1), $linea);
        }
        $ayuda->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $libro->setActiveSheetIndex(0);

        return $libro;
    }

    /** @param  list<string>  $categorias */
    public function descargar(array $categorias): Response
    {
        $archivo = tempnam(sys_get_temp_dir(), 'plantilla');
        try {
            (new Xlsx($this->libro($categorias)))->save($archivo);
            $contenido = (string) file_get_contents($archivo);
        } finally {
            @unlink($archivo);
        }
        $nombre = 'plantilla_promesas_futuras.xlsx';

        return response($contenido, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => HeaderUtils::makeDisposition('attachment', $nombre),
            'Content-Length' => strlen($contenido),
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }
}
