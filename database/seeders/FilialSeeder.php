<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\AreaResponsable;
use App\Models\Emisor;
use App\Models\Feriado;
use App\Models\InstruccionFrecuente;
use App\Models\ReglaDerivacion;
use App\Models\TipoDocumento;
use App\Models\TipoTramite;
use App\Models\UbicacionFisica;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Estructura real de la Filial Quillabamba de la UAC (datos del usuario): dirección, administración, coordinación
 * académica y escuelas con sus coordinadores, más el catálogo habitual de trámites. Los correos son provisionales
 * (@demo.example) hasta cargar los institucionales en Usuarios. Idempotente: reconoce por correo, siglas o nombre.
 */
class FilialSeeder extends Seeder
{
    /** Siglas => [nombre, siglas del área de la que depende, palabras clave]. */
    private const AREAS = [
        'DIR' => ['Dirección de Filial', null, ['dirección', 'resolución', 'convenio', 'licencia']],
        'ADM' => ['Administración', 'DIR', ['inventario', 'compras', 'presupuesto', 'pago', 'equipos']],
        'CA' => ['Coordinación Académica', 'DIR', ['encuesta', 'carga lectiva', 'horario', 'sílabo', 'matrícula', 'constancia']],
        'EPD' => ['Escuela Profesional de Derecho', 'DIR', ['derecho', 'consultorio jurídico', 'juzgado']],
        'EPC' => ['Escuela Profesional de Contabilidad', 'DIR', ['contabilidad', 'tributación', 'auditoría']],
        'EPE' => ['Escuela Profesional de Enfermería', 'DIR', ['enfermería', 'internado', 'campos clínicos', 'hospital']],
        'EPIC' => ['Escuela Profesional de Ingeniería Civil', 'DIR', ['obra', 'topografía', 'laboratorio de suelos']],
        'EPIS' => ['Escuela Profesional de Ingeniería de Sistemas', 'DIR', ['sistemas', 'software', 'laboratorio de cómputo']],
        'EPPS' => ['Escuela Profesional de Psicología', 'DIR', ['psicología', 'tamizaje', 'consejería']],
        'EPAD' => ['Escuela Profesional de Administración', 'DIR', ['administración de empresas', 'emprendimiento', 'marketing']],
    ];

    /** Correo provisional => [nombre, rol, siglas del área, titular|suplente]. */
    private const PERSONAS = [
        'estela.quispe@demo.example' => ['Dra. Estela Quispe Ramos', 'director', 'DIR', 'titular'],
        'enrique.florez@demo.example' => ['Enrique Florez Hurtado', 'administrativo', 'ADM', 'titular'],
        'paola.saldivar@demo.example' => ['Paola Indira Saldivar Nuñez', 'administrativo', 'ADM', 'suplente'],
        'rocio.huaycochea@demo.example' => ['Mgtr. Rocío Huaycochea Esquivel', 'coordinador', 'CA', 'titular'],
        'flor.acuna@demo.example' => ['Mgtr. Flor de María Acuña Palomino', 'coordinador', 'EPD', 'titular'],
        'cristian.contabilidad@demo.example' => ['Mgtr. Cristian', 'coordinador', 'EPC', 'titular'],
        'lisbeth.castro@demo.example' => ['Mgtr. Lisbeth Castro Cabrera', 'coordinador', 'EPE', 'titular'],
        'renato.motta@demo.example' => ['Mgtr. Renato Motta', 'coordinador', 'EPIC', 'titular'],
        'eric.garcia@demo.example' => ['Mgtr. Eric Boris Garcia', 'coordinador', 'EPIS', 'titular'],
        'juan.luna@demo.example' => ['Mgtr. Juan Andrés Luna Gallardo', 'coordinador', 'EPPS', 'titular'],
        'miguel.administracion@demo.example' => ['Mgtr. Miguel', 'coordinador', 'EPAD', 'titular'],
    ];

    /** Nombre => [plazo en días hábiles, quién aprueba el cierre, descripción]. */
    private const TIPOS_TRAMITE = [
        'Constancia de estudios' => [3, null, 'La pide el estudiante.'],
        'Certificado de estudios' => [7, 'director', 'Certificado oficial con notas; lo firma la dirección.'],
        'Constancia de egresado' => [5, null, 'Para el trámite de grado o trabajo.'],
        'Reserva de matrícula' => [5, 'coordinador', 'El estudiante deja de estudiar un semestre conservando su vacante.'],
        'Convalidación de asignaturas' => [15, 'coordinador', 'Reconocer asignaturas aprobadas en otra carrera o universidad.'],
        'Justificación de inasistencia' => [3, 'coordinador', 'A un examen o práctica, con sustento médico u oficial.'],
        'Rectificación de nota' => [5, 'coordinador', 'Corrección de una nota registrada por error.'],
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
    }

    /** @return array<string, Area> por siglas */
    private function areas(): array
    {
        $areas = [];
        $orden = 0;
        foreach (self::AREAS as $siglas => [$nombre, $padre, $palabras]) {
            $area = Area::where('siglas', $siglas)->first() ?? Area::where('nombre', $nombre)->first() ?? new Area(['nombre' => $nombre]);
            $area->forceFill([
                'nombre' => $nombre,
                'siglas' => $siglas,
                'parent_id' => $padre ? $areas[$padre]->id : null,
                'palabras_clave' => $palabras,
                'orden' => ++$orden,
                'activa' => true,
            ])->save();
            $areas[$siglas] = $area;
        }

        return $areas;
    }

    /** @param array<string, Area> $areas */
    private function personas(array $areas): void
    {
        foreach (self::PERSONAS as $email => [$nombre, $rol, $siglas, $tipo]) {
            $usuario = User::firstOrCreate(['email' => $email], ['name' => $nombre, 'activo' => true]);
            $usuario->syncRoles([$rol]);
            $area = $areas[$siglas];
            if (AreaResponsable::where('area_id', $area->id)->where('user_id', $usuario->id)->vigentes()->exists()) {
                continue;
            }
            AreaResponsable::create([
                'area_id' => $area->id, 'user_id' => $usuario->id, 'tipo' => $tipo,
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

        foreach ([
            '2026-01-01' => 'Año Nuevo', '2026-04-02' => 'Jueves Santo', '2026-04-03' => 'Viernes Santo', '2026-05-01' => 'Día del Trabajo',
            '2026-06-07' => 'Batalla de Arica y Día de la Bandera', '2026-06-29' => 'San Pedro y San Pablo', '2026-07-23' => 'Día de la Fuerza Aérea',
            '2026-07-28' => 'Fiestas Patrias', '2026-07-29' => 'Fiestas Patrias', '2026-08-06' => 'Batalla de Junín', '2026-08-30' => 'Santa Rosa de Lima',
            '2026-10-08' => 'Combate de Angamos', '2026-11-01' => 'Día de Todos los Santos', '2026-12-08' => 'Inmaculada Concepción',
            '2026-12-09' => 'Batalla de Ayacucho', '2026-12-25' => 'Navidad',
        ] as $fecha => $descripcion) {
            Feriado::firstOrCreate(['fecha' => $fecha, 'area_id' => null], ['descripcion' => $descripcion]);
        }

        foreach ([
            'Oficio' => 'director', 'Oficio múltiple' => 'director', 'Carta' => 'director', 'Informe' => 'coordinador', 'Memorando' => 'director',
            'Constancia' => 'director', 'Resolución de Dirección' => 'director', 'Solicitud (FUT)' => 'director',
        ] as $nombre => $aprueba) {
            TipoDocumento::firstOrCreate(['nombre' => $nombre], ['aprueba_salida' => $aprueba, 'activo' => true]);
        }

        foreach ([
            ['Municipalidad Provincial de La Convención', 'externo'], ['UGEL La Convención', 'externo'], ['Red de Salud La Convención', 'externo'],
            ['Hospital de Quillabamba', 'externo'], ['Vicerrectorado Académico', 'interno'], ['Dirección General de Administración', 'interno'],
            ['Dirección de Servicios Académicos', 'interno'], ['Oficina de Grados y Títulos', 'interno'], ['Dirección de Recursos Humanos', 'interno'],
        ] as [$nombre, $tipo]) {
            Emisor::firstOrCreate(['nombre' => $nombre], ['tipo' => $tipo, 'activo' => true]);
        }

        foreach (['Atender y responder', 'Para conocimiento y fines', 'Informar a la brevedad', 'Coordinar con el área', 'Emitir constancia',
            'Evaluar y emitir informe', 'Proyectar resolución', 'Archivar'] as $i => $texto) {
            InstruccionFrecuente::firstOrCreate(['texto' => $texto], ['orden' => $i + 1, 'activa' => true]);
        }

        foreach (['Archivador A · Administración', 'Archivador B · Dirección', 'Archivador C · Coordinación Académica'] as $nombre) {
            UbicacionFisica::firstOrCreate(['nombre' => $nombre], ['activa' => true]);
        }

        $reglas = [
            ['Campos clínicos a Enfermería', 'Campos clínicos e internado', ['internado', 'campos clínicos'], 'EPE', 10],
            ['Encuestas a Coordinación Académica', 'Requerimiento de información', ['encuesta'], 'CA', 20],
            ['Constancias a Coordinación Académica', 'Constancia de estudios', ['constancia de estudios'], 'CA', 30],
            ['Licencias docentes a Dirección', 'Licencia docente', ['licencia'], 'DIR', 40],
        ];
        foreach ($reglas as [$nombre, $tipo, $palabras, $siglas, $prioridad]) {
            ReglaDerivacion::firstOrCreate(['nombre' => $nombre], [
                'tipo_tramite_id' => $tipos[$tipo]->id, 'condicion' => ['palabras_clave' => $palabras, 'remitentes' => []],
                'area_destino_id' => $areas[$siglas]->id, 'prioridad' => $prioridad, 'activa' => true,
            ]);
        }
    }
}
