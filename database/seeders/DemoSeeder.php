<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\AreaResponsable;
use App\Models\Emisor;
use App\Models\Feriado;
use App\Models\InstruccionFrecuente;
use App\Models\PlantillaDocumento;
use App\Models\PlazoArea;
use App\Models\ReglaDerivacion;
use App\Models\TipoDocumento;
use App\Models\TipoTramite;
use App\Models\UbicacionFisica;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

/**
 * Datos de prueba de una filial (Quillabamba de la UAC), solo en local: dirección, oficinas, escuelas con sus
 * coordinaciones de especialidad, personal y docentes, y el catálogo de trámites habituales. Personas ficticias
 * (correo @demo.example). Idempotente: reconoce lo que ya existe por siglas o nombre y no lo pisa.
 */
class DemoSeeder extends Seeder
{
    /** Siglas => [nombre, siglas del área de la que depende, palabras clave]. */
    private const AREAS = [
        'DIR' => ['Dirección de Filial', null, ['dirección', 'resolución', 'convenio', 'licencia']],
        'MP' => ['Mesa de partes', 'DIR', ['oficio', 'solicitud', 'carta']],
        'SAC' => ['Secretaría Académica', 'DIR', ['constancia', 'certificado', 'matrícula', 'egresado']],
        'UA' => ['Unidad de Administración', 'DIR', ['inventario', 'compras', 'presupuesto', 'equipos']],
        'CA' => ['Coordinación Académica', 'DIR', ['encuesta', 'carga lectiva', 'horario', 'sílabo']],
        'OBU' => ['Oficina de Bienestar Universitario', 'DIR', ['salud', 'becas', 'bienestar', 'psicología']],
        'BIB' => ['Biblioteca', 'DIR', ['libros', 'préstamo', 'repositorio']],
        'EPD' => ['Escuela Profesional de Derecho', 'DIR', ['derecho', 'consultorio jurídico', 'juzgado']],
        'EPC' => ['Escuela Profesional de Contabilidad', 'DIR', ['contabilidad', 'tributación', 'auditoría']],
        'EPAD' => ['Escuela Profesional de Administración', 'DIR', ['administración', 'emprendimiento', 'marketing']],
        'EPIS' => ['Escuela Profesional de Ingeniería de Sistemas', 'DIR', ['sistemas', 'software', 'laboratorio de cómputo']],
        'EPIC' => ['Escuela Profesional de Ingeniería Civil', 'DIR', ['obra', 'topografía', 'laboratorio de suelos']],
        'EPE' => ['Escuela Profesional de Enfermería', 'DIR', ['enfermería', 'internado', 'campos clínicos', 'hospital']],
        'EPEDU' => ['Escuela Profesional de Educación', 'DIR', ['educación', 'práctica docente', 'ugel']],
        'EINI' => ['Especialidad de Educación Inicial', 'EPEDU', ['inicial', 'cuna', 'jardín']],
        'EPRI' => ['Especialidad de Educación Primaria', 'EPEDU', ['primaria']],
    ];

    /** Correo => [nombre, rol, siglas del área, titular|suplente|null]. Los docentes («otros») ven solo lo que se les asigna. */
    private const PERSONAS = [
        'director@demo.example' => ['Dr. Julio César Paredes Loayza', 'director', 'DIR', 'titular'],
        'administrativo@demo.example' => ['Rosa Elena Quispe Huamán', 'administrativo', 'MP', 'titular'],
        'mesadepartes2@demo.example' => ['Marco Antonio Ccori Sánchez', 'administrativo', 'MP', 'suplente'],
        'secretaria.academica@demo.example' => ['Lic. Gladys Pumacahua Torres', 'coordinador', 'SAC', 'titular'],
        'administracion@demo.example' => ['CPC Wilber Condori Mamani', 'coordinador', 'UA', 'titular'],
        'coord.academica@demo.example' => ['Mg. Patricia Salas Vargas', 'coordinador', 'CA', 'titular'],
        'bienestar@demo.example' => ['Lic. Yesenia Huillca Arce', 'coordinador', 'OBU', 'titular'],
        'biblioteca@demo.example' => ['Bach. Raúl Ttito Choque', 'coordinador', 'BIB', 'titular'],
        'coord.derecho@demo.example' => ['Abog. Hernán Zúñiga Farfán', 'coordinador', 'EPD', 'titular'],
        'coord.contabilidad@demo.example' => ['CPC Elena Rozas Cáceres', 'coordinador', 'EPC', 'titular'],
        'coord.administracion@demo.example' => ['Lic. Fredy Callo Quispe', 'coordinador', 'EPAD', 'titular'],
        'coordinador@demo.example' => ['Ing. Luis Alberto Huamaní Soto', 'coordinador', 'EPIS', 'titular'],
        'coord.civil@demo.example' => ['Ing. Alberto Mendoza Ugarte', 'coordinador', 'EPIC', 'titular'],
        'coord.enfermeria@demo.example' => ['Lic. Carmen Ugarte Puma', 'coordinador', 'EPE', 'titular'],
        'coord.educacion@demo.example' => ['Mg. Nelly Arce Gamarra', 'coordinador', 'EPEDU', 'titular'],
        'esp.inicial@demo.example' => ['Lic. Rocío Pacheco Luna', 'coordinador', 'EINI', 'titular'],
        'esp.primaria@demo.example' => ['Lic. Edwin Sullca Quispe', 'coordinador', 'EPRI', 'titular'],
        'docente.derecho@demo.example' => ['Mg. Javier Loaiza Puente', 'otros', null, null],
        'docente.sistemas@demo.example' => ['Ing. Karina Valdez Ochoa', 'otros', null, null],
        'docente.enfermeria@demo.example' => ['Lic. Teresa Ayma Cruz', 'otros', null, null],
        'docente.contabilidad@demo.example' => ['CPC Óscar Huallpa Rojas', 'otros', null, null],
        'docente.educacion@demo.example' => ['Lic. Milagros Tupa Alvarez', 'otros', null, null],
    ];

    /** Nombre => [plazo en días hábiles, quién aprueba el cierre, descripción]. */
    private const TIPOS_TRAMITE = [
        'Constancia de estudios' => [3, null, 'La pide el estudiante; la emite Secretaría Académica.'],
        'Certificado de estudios' => [7, 'director', 'Certificado oficial con notas; lo firma el director.'],
        'Constancia de egresado' => [5, null, 'Para el trámite de grado o trabajo.'],
        'Reserva de matrícula' => [5, 'coordinador', 'El estudiante deja de estudiar un semestre conservando su vacante.'],
        'Convalidación de asignaturas' => [15, 'coordinador', 'Reconocer asignaturas aprobadas en otra carrera o universidad.'],
        'Justificación de inasistencia' => [3, 'coordinador', 'A un examen o práctica, con sustento médico u oficial.'],
        'Rectificación de nota' => [5, 'coordinador', 'Corrección de una nota registrada por error; la revisa el docente.'],
        'Licencia docente' => [5, 'director', 'Permiso del docente por salud, capacitación o motivos personales.'],
        'Convenio de prácticas pre profesionales' => [15, 'director', 'Con instituciones donde los estudiantes hacen sus prácticas.'],
        'Campos clínicos e internado' => [10, 'director', 'Plazas en hospitales y centros de salud para Enfermería.'],
        'Requerimiento de información' => [5, 'director', 'Piden datos o documentos con fecha de entrega.'],
        'Solicitud de recursos o docentes' => [10, 'director', 'Piden personal, ambientes o equipos.'],
        'Convocatoria a reunión' => [3, null, 'Citan a una reunión o comisión.'],
        'Invitación a evento' => [null, null, 'Invitan a una ceremonia o actividad; para conocimiento.'],
        'Comunicación informativa' => [null, null, 'Informan sin pedir respuesta.'],
    ];

    public function run(): void
    {
        $areas = $this->areas();
        $this->personas($areas);
        $this->catalogo($areas);

        // Buzón de prueba con los correos ficticios de los tests: `php artisan correo:importar`.
        File::copyDirectory(base_path('tests/fixtures/correos'), config('tramite.correo.directorio'));
    }

    /** @return array<string, Area> por siglas */
    private function areas(): array
    {
        $areas = [];
        $orden = 0;
        foreach (self::AREAS as $siglas => [$nombre, $padre, $palabras]) {
            // Por siglas, o por nombre sin siglas: no duplica un área que ya cargó alguien desde el panel.
            $area = Area::where('siglas', $siglas)->first()
                ?? Area::whereNull('siglas')->where('nombre', $nombre)->first()
                ?? ($siglas === 'DIR' ? Area::whereNull('parent_id')->where('nombre', 'Dirección')->first() : null)
                ?? new Area(['nombre' => $nombre]);
            $area->fill([
                'siglas' => $siglas,
                'parent_id' => $padre ? $areas[$padre]->id : null,
                'orden' => ++$orden,
                'activa' => true,
            ]);
            if (! $area->exists) {
                $area->fill(['palabras_clave' => $palabras, 'descripcion' => null]);
            }
            $area->save();
            $areas[$siglas] = $area;
        }

        // Las áreas genéricas del ejemplo anterior quedan inactivas (nunca se borran).
        Area::whereIn('nombre', ['Escuela Profesional de ejemplo', 'Laboratorio de cómputo'])->update(['activa' => false]);

        return $areas;
    }

    /** @param array<string, Area> $areas */
    private function personas(array $areas): void
    {
        foreach (self::PERSONAS as $email => [$nombre, $rol, $siglas, $tipo]) {
            $usuario = User::firstOrCreate(['email' => $email], ['name' => $nombre, 'activo' => true]);
            $usuario->forceFill(['name' => $nombre])->save();
            $usuario->syncRoles([$rol]);
            if (! $siglas) {
                continue;
            }
            $area = $areas[$siglas];
            if (AreaResponsable::where('area_id', $area->id)->where('user_id', $usuario->id)->vigentes()->exists()) {
                continue;
            }
            // Si el área ya tiene titular (p. ej. cargado desde el panel), esta persona queda de suplente.
            $hayTitular = AreaResponsable::where('area_id', $area->id)->where('tipo', 'titular')->vigentes()->exists();
            AreaResponsable::create([
                'area_id' => $area->id, 'user_id' => $usuario->id,
                'tipo' => $tipo === 'titular' && $hayTitular ? 'suplente' : $tipo,
                'vigente_desde' => today()->startOfYear()->toDateString(),
            ]);
        }
    }

    /** @param array<string, Area> $areas */
    private function catalogo(array $areas): void
    {
        $tipos = [];
        foreach (self::TIPOS_TRAMITE as $nombre => [$plazo, $aprueba, $descripcion]) {
            $tipos[$nombre] = TipoTramite::firstOrCreate(['nombre' => $nombre], [
                'plazo_dias' => $plazo, 'tipo_dias' => 'habiles', 'aprueba_cierre' => $aprueba, 'descripcion' => $descripcion, 'activo' => true,
            ]);
        }
        // La convalidación en Derecho exige revisar sílabos de otra universidad: más plazo.
        PlazoArea::firstOrCreate(['tipo_tramite_id' => $tipos['Convalidación de asignaturas']->id, 'area_id' => $areas['EPD']->id], ['plazo_dias' => 20]);

        foreach ([
            '2026-01-01' => 'Año Nuevo', '2026-04-02' => 'Jueves Santo', '2026-04-03' => 'Viernes Santo', '2026-05-01' => 'Día del Trabajo',
            '2026-06-07' => 'Batalla de Arica y Día de la Bandera', '2026-06-29' => 'San Pedro y San Pablo', '2026-07-23' => 'Día de la Fuerza Aérea',
            '2026-07-28' => 'Fiestas Patrias', '2026-07-29' => 'Fiestas Patrias', '2026-08-06' => 'Batalla de Junín', '2026-08-30' => 'Santa Rosa de Lima',
            '2026-10-08' => 'Combate de Angamos', '2026-11-01' => 'Día de Todos los Santos', '2026-12-08' => 'Inmaculada Concepción',
            '2026-12-09' => 'Batalla de Ayacucho', '2026-12-25' => 'Navidad',
        ] as $fecha => $descripcion) {
            Feriado::firstOrCreate(['fecha' => $fecha, 'area_id' => null], ['descripcion' => $descripcion]);
        }

        $documentos = [];
        foreach ([
            'Oficio' => 'director', 'Oficio múltiple' => 'director', 'Carta' => 'director', 'Informe' => 'coordinador', 'Memorando' => 'director',
            'Constancia' => 'director', 'Resolución de Dirección' => 'director', 'Solicitud (FUT)' => 'director',
        ] as $nombre => $aprueba) {
            $documentos[$nombre] = TipoDocumento::firstOrCreate(['nombre' => $nombre], ['aprueba_salida' => $aprueba, 'activo' => true]);
        }

        foreach ([
            ['Municipalidad Provincial de La Convención', 'externo'], ['UGEL La Convención', 'externo'], ['Red de Salud La Convención', 'externo'],
            ['Hospital de Quillabamba', 'externo'], ['Juzgado Mixto de La Convención', 'externo'], ['I.E. Emblemática Manco II', 'externo'],
            ['Vicerrectorado Académico', 'interno'], ['Dirección General de Administración', 'interno'], ['Dirección de Servicios Académicos', 'interno'],
            ['Oficina de Grados y Títulos', 'interno'], ['Dirección de Recursos Humanos', 'interno'],
            // Estudiantes y docentes que presentan su solicitud (FUT) en mesa de partes.
            ['Luz Marina Quispe Ccama', 'interno'], ['Kevin Arturo Mamani Ttito', 'interno'], ['Yaneth Huamán Condori', 'interno'],
            ['Brayan Choque Ccahuana', 'interno'], ['Sheyla Puma Quispe', 'interno'],
        ] as [$nombre, $tipo]) {
            Emisor::firstOrCreate(['nombre' => $nombre], ['tipo' => $tipo, 'activo' => true]);
        }

        foreach (['Atender y responder', 'Para conocimiento y fines', 'Informar a la brevedad', 'Coordinar con el área', 'Emitir constancia',
            'Evaluar y emitir informe', 'Proyectar resolución', 'Archivar'] as $i => $texto) {
            InstruccionFrecuente::firstOrCreate(['texto' => $texto], ['orden' => $i + 1, 'activa' => true]);
        }

        foreach (['Archivador A · Mesa de partes', 'Archivador B · Dirección', 'Archivador C · Secretaría Académica'] as $nombre) {
            UbicacionFisica::firstOrCreate(['nombre' => $nombre], ['activa' => true]);
        }

        PlantillaDocumento::firstOrCreate(['nombre' => 'Respuesta a requerimiento'], [
            'tipo_documento_id' => $documentos['Oficio']->id, 'activa' => true,
            'asunto' => 'Atención al {{expediente.documento}}',
            'cuerpo' => "Señor(a):\n{{remitente}}\n\nPresente.\n\nEs grato dirigirme a usted en atención al documento {{expediente.documento}}, registrado con el {{expediente.numero}} ({{expediente.codigo}}), sobre «{{expediente.asunto}}».\n\nAl respecto, remitimos la información solicitada.\n\nAtentamente,\n\n{{area}}\nQuillabamba, {{fecha}}",
        ]);
        PlantillaDocumento::firstOrCreate(['nombre' => 'Constancia de estudios'], [
            'tipo_documento_id' => $documentos['Constancia']->id, 'activa' => true,
            'asunto' => 'Constancia de estudios',
            'cuerpo' => "La Secretaría Académica de la Filial Quillabamba hace constar que {{remitente}} es estudiante regular de la universidad en el semestre académico 2026-II, según los registros a la fecha.\n\nSe expide la presente a solicitud del interesado ({{expediente.numero}}), para los fines que estime conveniente.\n\nQuillabamba, {{fecha}}",
        ]);
        PlantillaDocumento::firstOrCreate(['nombre' => 'Memorando a docentes'], [
            'tipo_documento_id' => $documentos['Memorando']->id, 'activa' => true,
            'asunto' => 'Atención al {{expediente.documento}}',
            'cuerpo' => "A los docentes de la {{area}}:\n\nEn atención al {{expediente.documento}} ({{expediente.codigo}}), sobre «{{expediente.asunto}}», se les solicita cumplir con lo requerido dentro del plazo.\n\nQuillabamba, {{fecha}}",
        ]);

        $reglas = [
            ['Constancias y certificados a Secretaría Académica', 'Constancia de estudios', ['constancia', 'certificado de estudios'], [], 'SAC', 10],
            ['Campos clínicos a Enfermería', 'Campos clínicos e internado', ['internado', 'campos clínicos'], [], 'EPE', 20],
            ['Prácticas de la UGEL a Educación', 'Convenio de prácticas pre profesionales', ['práctica'], ['ugellaconvencion.gob.pe'], 'EPEDU', 30],
            ['Encuestas a Coordinación Académica', 'Requerimiento de información', ['encuesta'], [], 'CA', 40],
            ['Licencias docentes a Dirección', 'Licencia docente', ['licencia'], [], 'DIR', 50],
        ];
        foreach ($reglas as [$nombre, $tipo, $palabras, $remitentes, $siglas, $prioridad]) {
            ReglaDerivacion::firstOrCreate(['nombre' => $nombre], [
                'tipo_tramite_id' => $tipos[$tipo]->id, 'condicion' => ['palabras_clave' => $palabras, 'remitentes' => $remitentes],
                'area_destino_id' => $areas[$siglas]->id, 'prioridad' => $prioridad, 'activa' => true,
            ]);
        }
    }
}
