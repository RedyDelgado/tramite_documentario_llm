import { useState } from 'react';
import { FormField } from '@/components/forms/FormField';
import type { OpcionEmisor } from '@/types';
import { EmisorCombobox } from './EmisorCombobox';

type Props = {
    opciones: OpcionEmisor[];
    emisorId: number | null;
    institucionId: number | null;
    onEmisor: (id: number | null) => void;
    onInstitucion: (id: number | null) => void;
    errores: { emisor?: string; institucion?: string };
    // Quien firma según el documento, si aún no está en el catálogo.
    sugerido?: string;
    requerido?: boolean;
};

/**
 * Quién emite (una persona o una institución) y a qué institución pertenece: el director del hospital es del Hospital
 * de Quillabamba; la municipalidad emite como institución; un ciudadano no pertenece a ninguna.
 */
export function RemitenteCampos({ opciones: iniciales, emisorId, institucionId, onEmisor, onInstitucion, errores, sugerido, requerido }: Props) {
    const [opciones, setOpciones] = useState(iniciales);
    const [errorEmisor, setErrorEmisor] = useState<string>();
    const [errorInstitucion, setErrorInstitucion] = useState<string>();
    const agregar = (nueva: OpcionEmisor) => setOpciones((o) => [...o, nueva]);
    const emisor = opciones.find((o) => o.value === emisorId);
    const esInstitucion = emisor?.clase === 'institucion';

    const elegirEmisor = (id: number | null) => {
        onEmisor(id);
        const elegido = opciones.find((o) => o.value === id);
        // Una institución no pertenece a otra; una persona trae su institución habitual si aún no se eligió.
        if (elegido?.clase === 'institucion') onInstitucion(null);
        else if (elegido?.institucion_id && !institucionId) onInstitucion(elegido.institucion_id);
    };

    return (
        <>
            <FormField
                etiqueta="Emisor"
                ayuda="Quien firma: una persona o, si no hay firma personal, la institución."
                requerido={requerido}
                error={errorEmisor ?? errores.emisor}
            >
                {(c) => (
                    <EmisorCombobox
                        {...c}
                        opciones={opciones}
                        value={emisorId}
                        sugerido={sugerido}
                        onCreado={agregar}
                        onChange={elegirEmisor}
                        onError={setErrorEmisor}
                    />
                )}
            </FormField>
            <FormField
                etiqueta="Institución"
                ayuda={esInstitucion ? 'El emisor ya es una institución.' : 'A la que pertenece; vacía si es una persona sin institución.'}
                error={errorInstitucion ?? errores.institucion}
            >
                {(c) => (
                    <EmisorCombobox
                        {...c}
                        // Remontar al crear un emisor nuevo suma a la lista la institución recién creada.
                        key={opciones.length}
                        opciones={opciones.filter((o) => o.clase === 'institucion')}
                        value={esInstitucion ? null : institucionId}
                        clase="institucion"
                        disabled={esInstitucion}
                        placeholder={esInstitucion ? '—' : 'Escribe para buscar o crear'}
                        onCreado={agregar}
                        onChange={(id) => !esInstitucion && onInstitucion(id)}
                        onError={setErrorInstitucion}
                    />
                )}
            </FormField>
        </>
    );
}
