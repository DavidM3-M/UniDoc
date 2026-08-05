<?php
// Define el espacio de nombres para organizar las constantes relacionadas con el RUT.
namespace App\Constants\ConstRut;
// Esta clase contiene las secciones del código CIIU (Clasificación Industrial Internacional Uniforme),
// utilizadas para categorizar actividades económicas en el RUT.
class CodigoCiiu
{
    // Sección A: Actividades relacionadas con el sector primario (agricultura, pesca, etc.)
    public const SECCION_A = 'Agricultura, ganadería, caza, silvicultura y pesca';
    // Sección B: Minería y explotación de canteras.
    public const SECCION_B = 'Explotación de minas y canteras';
    // Sección C: Procesos de transformación de materias primas (industria manufacturera).
    public const SECCION_C = 'Industria manufacturera';
    // Sección D: Distribución de servicios públicos como electricidad, gas y aire acondicionado.
    public const SECCION_D = 'Suministro de electricidad, gas, vapor y aire acondicionado';
    // Sección E: Servicios de agua, residuos y saneamiento ambiental.
    public const SECCION_E = 'Suministro de agua, alcantarillado, gestión de residuos y actividades de saneamiento';
    // Sección F: Actividades relacionadas con la construcción.
    public const SECCION_F = 'Construcción';
    // Sección G: Comercio y reparación de vehículos automotores y motocicletas.
    public const SECCION_G = 'Comercio al por mayor y al por menor; reparación de vehículos automotores y motocicletas';
    // Sección H: Transporte de bienes y personas, así como almacenamiento.
    public const SECCION_H = 'Transporte y almacenamiento';
    // Sección I: Servicios de alojamiento y comida (hoteles, restaurantes, etc.)
    public const SECCION_I = 'Alojamiento y servicios de comida';
    // Sección J: Telecomunicaciones, tecnología de la información, etc.
    public const SECCION_J = 'Información y comunicaciones';
    // Sección K: Banca, seguros y demás actividades financieras.
    public const SECCION_K = 'Actividades financieras y de seguros';
    // Sección L: Actividades relacionadas con bienes raíces e inmuebles.
    public const SECCION_L = 'Actividades inmobiliarias';
    // Sección M: Actividades profesionales, técnicas y científicas.
    public const SECCION_M = 'Actividades profesionales, científicas y técnicas';
    // Sección N: Servicios administrativos y de apoyo a empresas.
    public const SECCION_N = 'Actividades administrativas y de servicios auxiliares';
    // Sección O: Sector público, defensa nacional y seguridad social.
    public const SECCION_O = 'Administración pública y defensa; seguridad social obligatoria';
    // Sección P: Servicios de educación en todos los niveles.
    public const SECCION_P = 'Educación';
    // Sección Q: Servicios de salud y asistencia social.
    public const SECCION_Q = 'Actividades de salud humana y de asistencia social';
    // Sección R: Artes, cultura, deportes y entretenimiento.
    public const SECCION_R = 'Artes, entretenimiento y recreación';
    // Sección S: Servicios diversos como peluquerías, lavanderías, etc.
    public const SECCION_S = 'Otras actividades de servicios';
    // Sección T: Actividades realizadas por hogares, tanto como empleadores como productores de bienes para consumo propio.
    public const SECCION_T = 'Actividades de los hogares como empleadores; actividades de los hogares como productores de bienes y servicios para uso propio';
    // Sección U: Actividades realizadas por organismos internacionales y extraterritoriales.
    public const SECCION_U = 'Organizaciones y organismos extraterritoriales';

    // Retorna todas las secciones definidas anteriormente como un arreglo.
    public static function all(): array
    {
        return [
            self::SECCION_A,
            self::SECCION_B,
            self::SECCION_C,
            self::SECCION_D,
            self::SECCION_E,
            self::SECCION_F,
            self::SECCION_G,
            self::SECCION_H,
            self::SECCION_I,
            self::SECCION_J,
            self::SECCION_K,
            self::SECCION_L,
            self::SECCION_M,
            self::SECCION_N,
            self::SECCION_O,
            self::SECCION_P,
            self::SECCION_Q,
            self::SECCION_R,
            self::SECCION_S,
            self::SECCION_T,
            self::SECCION_U,
        ];
    }
}
