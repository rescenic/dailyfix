-- phpMyAdmin SQL Dump
-- version 5.1.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Apr 17, 2026 at 06:25 AM
-- Server version: 10.4.20-MariaDB
-- PHP Version: 8.0.8

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `dailyfix`
--

-- --------------------------------------------------------

--
-- Table structure for table `absensi`
--

CREATE TABLE `absensi` (
  `id` int(11) NOT NULL,
  `karyawan_id` int(11) NOT NULL,
  `jadwal_id` int(11) DEFAULT NULL,
  `shift_id` int(11) DEFAULT NULL,
  `lokasi_id` int(11) DEFAULT NULL,
  `tanggal` date NOT NULL,
  `waktu_masuk` datetime DEFAULT NULL,
  `lat_masuk` decimal(10,8) DEFAULT NULL,
  `lng_masuk` decimal(11,8) DEFAULT NULL,
  `jarak_masuk` int(11) DEFAULT NULL COMMENT 'Jarak dari lokasi kerja dalam meter',
  `foto_masuk` mediumtext COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `waktu_keluar` datetime DEFAULT NULL,
  `lat_keluar` decimal(10,8) DEFAULT NULL,
  `lng_keluar` decimal(11,8) DEFAULT NULL,
  `jarak_keluar` int(11) DEFAULT NULL,
  `foto_keluar` mediumtext COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status_kehadiran` enum('hadir','terlambat','absen','izin','sakit','libur') COLLATE utf8mb4_unicode_ci DEFAULT 'absen',
  `terlambat_detik` int(11) DEFAULT 0 COMMENT 'Durasi keterlambatan dalam detik',
  `pulang_cepat_detik` int(11) NOT NULL DEFAULT 0,
  `durasi_kerja` int(11) DEFAULT NULL COMMENT 'Durasi kerja dalam menit',
  `keterangan` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `device_info` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `departemen`
--

CREATE TABLE `departemen` (
  `id` int(11) NOT NULL,
  `perusahaan_id` int(11) NOT NULL,
  `nama` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `keterangan` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `departemen`
--

INSERT INTO `departemen` (`id`, `perusahaan_id`, `nama`, `keterangan`, `created_at`) VALUES
(25, 1, 'Umum', '', '2026-03-27 14:40:15'),
(26, 1, 'Keuangan', '', '2026-03-27 14:40:20'),
(27, 1, 'SIMRS', '', '2026-03-27 14:40:23'),
(28, 1, 'Direksi', '', '2026-03-27 14:40:31');

-- --------------------------------------------------------

--
-- Table structure for table `izin`
--

CREATE TABLE `izin` (
  `id` int(11) NOT NULL,
  `karyawan_id` int(11) NOT NULL,
  `jenis` enum('izin','sakit','cuti','dinas') COLLATE utf8mb4_unicode_ci NOT NULL,
  `tanggal_mulai` date NOT NULL,
  `tanggal_selesai` date NOT NULL,
  `keterangan` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dokumen` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('pending','disetujui','ditolak') COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `disetujui_oleh` int(11) DEFAULT NULL,
  `catatan_atasan` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `jabatan`
--

CREATE TABLE `jabatan` (
  `id` int(11) NOT NULL,
  `perusahaan_id` int(11) NOT NULL,
  `nama` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `keterangan` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `jabatan`
--

INSERT INTO `jabatan` (`id`, `perusahaan_id`, `nama`, `keterangan`, `created_at`) VALUES
(26, 1, 'IT Software', 'IT Software dan penanggung jawab SIMRS', '2026-03-27 14:39:43'),
(27, 1, 'IT Hardware', '-', '2026-03-27 14:39:51'),
(28, 1, 'Direktur', '', '2026-03-27 14:39:55'),
(29, 1, 'Kasir', '', '2026-03-27 14:39:59'),
(30, 1, 'Admisi', '', '2026-03-27 14:40:05');

-- --------------------------------------------------------

--
-- Table structure for table `jadwal`
--

CREATE TABLE `jadwal` (
  `id` int(11) NOT NULL,
  `perusahaan_id` int(11) NOT NULL,
  `nama` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `shift_id` int(11) NOT NULL,
  `lokasi_id_unused` int(11) DEFAULT NULL,
  `berlaku_dari` date NOT NULL,
  `berlaku_sampai` date DEFAULT NULL,
  `hari_kerja` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Array hari kerja: [1,2,3,4,5] = Senin-Jumat' CHECK (json_valid(`hari_kerja`)),
  `keterangan` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('aktif','nonaktif') COLLATE utf8mb4_unicode_ci DEFAULT 'aktif',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `jadwal`
--

INSERT INTO `jadwal` (`id`, `perusahaan_id`, `nama`, `shift_id`, `lokasi_id_unused`, `berlaku_dari`, `berlaku_sampai`, `hari_kerja`, `keterangan`, `status`, `created_at`, `updated_at`) VALUES
(15, 1, 'Kantor1', 23, 5, '2026-03-27', NULL, '[1,2,3,4,5]', '', 'aktif', '2026-03-27 14:43:06', '2026-03-27 14:43:06'),
(16, 1, 'Kantor2', 24, 5, '2026-03-27', NULL, '[6]', '', 'aktif', '2026-03-27 14:43:21', '2026-03-27 14:43:21'),
(17, 1, 'Pagi', 25, 5, '2026-04-16', NULL, '[1,2,3,4,5,6,7]', 'Nakes Pagi', 'aktif', '2026-04-16 12:13:48', '2026-04-16 12:13:48'),
(18, 1, 'Siang', 26, 5, '2026-04-16', NULL, '[1,2,3,4,5,6,7]', 'Nakes Siang', 'aktif', '2026-04-16 12:14:20', '2026-04-16 12:14:20'),
(19, 1, 'Malam', 27, 5, '2026-04-16', NULL, '[1,2,3,4,5,6,7]', 'Nakes Malam', 'aktif', '2026-04-16 12:14:42', '2026-04-16 12:14:42');

-- --------------------------------------------------------

--
-- Table structure for table `jadwal_karyawan`
--

CREATE TABLE `jadwal_karyawan` (
  `id` int(11) NOT NULL,
  `karyawan_id` int(11) NOT NULL,
  `jadwal_id` int(11) NOT NULL,
  `berlaku_dari` date NOT NULL,
  `berlaku_sampai` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `karyawan`
--

CREATE TABLE `karyawan` (
  `id` int(11) NOT NULL,
  `perusahaan_id` int(11) NOT NULL,
  `nik` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nama` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telepon` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `jabatan_id` int(11) DEFAULT NULL,
  `departemen_id` int(11) DEFAULT NULL,
  `lokasi_id` int(11) DEFAULT NULL,
  `foto` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tanggal_bergabung` date DEFAULT NULL,
  `role` enum('admin','karyawan','manager') COLLATE utf8mb4_unicode_ci DEFAULT 'karyawan',
  `status` enum('aktif','nonaktif','cuti') COLLATE utf8mb4_unicode_ci DEFAULT 'aktif',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `karyawan`
--

INSERT INTO `karyawan` (`id`, `perusahaan_id`, `nik`, `nama`, `email`, `password`, `telepon`, `jabatan_id`, `departemen_id`, `lokasi_id`, `foto`, `tanggal_bergabung`, `role`, `status`, `created_at`, `updated_at`) VALUES
(6, 1, '16216001', 'M Wira', 'wiramuhammad16@gmail.com', '$2y$10$e57H2WhtYfyOXkydbffbAON9CCs3pq8.bMFToFXuisdyuNUw5GZ.K', '082177846209', 26, 27, 5, NULL, '2026-03-25', 'admin', 'aktif', '2026-03-25 07:26:49', '2026-04-16 12:08:56'),
(17, 1, '16216090', 'M WIRA SATRI BUANA', 'satria@gmail.com', '$2y$10$BbM10my3J7ialmPEoEX2YOmhCJDb7aXAI95lwRSQC3wjkTJFncg6O', '082177846209', 28, 26, NULL, NULL, '2026-04-17', 'karyawan', 'nonaktif', '2026-04-17 03:01:01', '2026-04-17 03:01:01'),
(19, 1, '162160078', 'SATRIA', 'wsatria630@gmail.com', '$2y$10$rO2xJzQX/vrHssrcYiKAdO199TuPOdR0FNw4yEjt0vWdXjaXJ3fO2', '', 28, 28, NULL, NULL, '2026-04-17', 'karyawan', 'aktif', '2026-04-17 03:49:27', '2026-04-17 04:20:37');

-- --------------------------------------------------------

--
-- Table structure for table `karyawan_lokasi`
--

CREATE TABLE `karyawan_lokasi` (
  `karyawan_id` int(11) NOT NULL,
  `lokasi_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `karyawan_lokasi`
--

INSERT INTO `karyawan_lokasi` (`karyawan_id`, `lokasi_id`) VALUES
(6, 5),
(17, 5),
(17, 16),
(19, 5),
(19, 16);

-- --------------------------------------------------------

--
-- Table structure for table `log_aktivitas`
--

CREATE TABLE `log_aktivitas` (
  `id` int(11) NOT NULL,
  `karyawan_id` int(11) DEFAULT NULL,
  `aksi` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `keterangan` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `log_aktivitas`
--

INSERT INTO `log_aktivitas` (`id`, `karyawan_id`, `aksi`, `keterangan`, `ip_address`, `created_at`) VALUES
(1, NULL, 'LOGIN', 'Login berhasil dari ::1', '::1', '2026-03-25 02:29:25'),
(2, NULL, 'LOGOUT', 'Logout', '::1', '2026-03-25 02:35:55'),
(3, NULL, 'LOGIN', 'Login berhasil dari ::1', '::1', '2026-03-25 02:36:05'),
(4, NULL, 'LOGOUT', 'Logout', '::1', '2026-03-25 02:36:28'),
(5, NULL, 'LOGIN', 'Login berhasil dari ::1', '::1', '2026-03-25 02:36:51'),
(6, NULL, 'LOGIN', 'Login berhasil dari 172.16.10.134', '172.16.10.134', '2026-03-25 02:44:28'),
(7, NULL, 'LOGOUT', 'Logout', '::1', '2026-03-25 02:49:03'),
(8, NULL, 'LOGIN', 'Login berhasil dari ::1', '::1', '2026-03-25 02:49:11'),
(9, NULL, 'LOGOUT', 'Logout', '::1', '2026-03-25 02:49:39'),
(10, NULL, 'LOGIN', 'Login berhasil dari ::1', '::1', '2026-03-25 02:49:47'),
(11, NULL, 'LOGOUT', 'Logout', '::1', '2026-03-25 02:51:01'),
(12, NULL, 'LOGIN', 'Login berhasil dari ::1', '::1', '2026-03-25 02:51:15'),
(13, NULL, 'ABSEN_MASUK', 'Absen masuk jam 09:51:39, jarak 11m', '::1', '2026-03-25 02:51:39'),
(14, NULL, 'LOGOUT', 'Logout', '::1', '2026-03-25 02:51:54'),
(15, NULL, 'LOGIN', 'Login berhasil dari ::1', '::1', '2026-03-25 02:52:02'),
(16, NULL, 'LOGOUT', 'Logout', '::1', '2026-03-25 03:01:22'),
(17, NULL, 'LOGIN', 'Login berhasil dari ::1', '::1', '2026-03-25 03:01:30'),
(18, NULL, 'ABSEN_KELUAR', 'Absen keluar jam 10:01:47, durasi 10 menit', '::1', '2026-03-25 03:01:47'),
(19, NULL, 'LOGOUT', 'Logout', '::1', '2026-03-25 03:15:11'),
(20, NULL, 'LOGIN', 'Login berhasil dari ::1', '::1', '2026-03-25 03:15:19'),
(21, NULL, 'LOGOUT', 'Logout', '::1', '2026-03-25 03:31:57'),
(22, NULL, 'LOGIN', 'Login berhasil dari ::1', '::1', '2026-03-25 03:34:12'),
(23, NULL, 'ABSEN_MASUK', 'Absen masuk jam 11:35:47, jarak 0m', '::1', '2026-03-25 04:35:47'),
(24, NULL, 'LOGOUT', 'Logout', '::1', '2026-03-25 04:36:07'),
(25, NULL, 'LOGIN', 'Login berhasil dari ::1', '::1', '2026-03-25 04:36:16'),
(26, NULL, 'LOGOUT', 'Logout', '::1', '2026-03-25 04:49:27'),
(27, NULL, 'LOGIN', 'Login berhasil dari ::1', '::1', '2026-03-25 04:49:36'),
(28, NULL, 'ABSEN_KELUAR', 'Absen keluar jam 11:50:12, durasi 14 menit', '::1', '2026-03-25 04:50:12'),
(29, NULL, 'LOGOUT', 'Logout', '::1', '2026-03-25 04:50:34'),
(30, NULL, 'LOGIN', 'Login berhasil dari ::1', '::1', '2026-03-25 04:51:04'),
(31, NULL, 'LOGOUT', 'Logout', '::1', '2026-03-25 06:29:20'),
(32, NULL, 'LOGIN', 'Login berhasil dari ::1', '::1', '2026-03-25 06:29:27'),
(33, NULL, 'ABSEN_MASUK', 'Masuk 2026-03-25 13:33:03, jarak:0m, acc:40m', '::1', '2026-03-25 06:33:03'),
(34, NULL, 'LOGOUT', 'Logout', '::1', '2026-03-25 06:48:31'),
(35, NULL, 'LOGIN', 'Login berhasil dari ::1', '::1', '2026-03-25 06:48:40'),
(36, NULL, 'LOGOUT', 'Logout', '::1', '2026-03-25 06:49:28'),
(37, NULL, 'LOGIN', 'Login berhasil dari ::1', '::1', '2026-03-25 06:54:24'),
(38, NULL, 'LOGOUT', 'Logout', '::1', '2026-03-25 06:54:27'),
(39, NULL, 'LOGIN', 'Login berhasil dari ::1', '::1', '2026-03-25 06:54:29'),
(40, NULL, 'ABSEN_MASUK', 'Masuk 2026-03-25 13:54:39, jarak:11m, acc:35m', '::1', '2026-03-25 06:54:39'),
(41, NULL, 'LOGOUT', 'Logout', '::1', '2026-03-25 06:54:44'),
(42, NULL, 'LOGIN', 'Login berhasil dari ::1', '::1', '2026-03-25 06:54:48'),
(43, NULL, 'LOGOUT', 'Logout', '::1', '2026-03-25 06:56:53'),
(44, 6, 'LOGIN', 'Login berhasil dari ::1', '::1', '2026-03-25 07:26:56'),
(45, 6, 'LOGOUT', 'Logout', '::1', '2026-03-25 07:27:03'),
(46, 6, 'LOGIN', 'Login berhasil dari ::1', '::1', '2026-03-25 07:27:31'),
(47, 6, 'LOGOUT', 'Logout', '::1', '2026-03-25 07:51:30'),
(48, 6, 'LOGIN', 'Login berhasil dari ::1', '::1', '2026-03-25 08:58:46'),
(49, 6, 'LOGOUT', 'Logout', '::1', '2026-03-25 09:01:00'),
(50, NULL, 'LOGIN', 'Login berhasil dari ::1', '::1', '2026-03-25 09:01:07'),
(51, NULL, 'LOGIN', 'Login berhasil dari 192.168.1.5', '192.168.1.5', '2026-03-25 13:41:23'),
(52, NULL, 'LOGOUT', 'Logout', '::1', '2026-03-25 15:35:00'),
(53, 6, 'LOGIN', 'Login berhasil dari ::1', '::1', '2026-03-25 15:35:12'),
(54, 6, 'LOGOUT', 'Logout', '::1', '2026-03-25 15:37:52'),
(55, NULL, 'LOGIN', 'Login berhasil dari ::1', '::1', '2026-03-25 15:38:09'),
(56, NULL, 'LOGOUT', 'Logout', '::1', '2026-03-25 15:38:37'),
(57, 6, 'LOGIN', 'Login berhasil dari ::1', '::1', '2026-03-25 15:39:02'),
(58, 6, 'LOGIN', 'Login berhasil dari 192.168.1.8', '192.168.1.8', '2026-03-25 15:57:30'),
(59, 6, 'LOGOUT', 'Logout', '::1', '2026-03-25 16:02:40'),
(60, NULL, 'LOGIN', 'Login berhasil dari ::1', '::1', '2026-03-25 16:03:06'),
(61, NULL, 'LOGOUT', 'Logout', '::1', '2026-03-25 16:03:23'),
(62, 6, 'LOGIN', 'Login berhasil dari ::1', '::1', '2026-03-25 16:03:28'),
(63, 6, 'LOGOUT', 'Logout', '::1', '2026-03-26 12:13:42'),
(64, 6, 'LOGIN', 'Login berhasil dari ::1', '::1', '2026-03-26 12:17:10'),
(65, 6, 'LOGOUT', 'Logout', '::1', '2026-03-26 12:43:21'),
(66, NULL, 'OTP_SENT', 'OTP dikirim ke wiramuhammad16@gmail.com', '::1', '2026-03-26 12:46:17'),
(67, NULL, 'OTP_SENT', 'OTP dikirim ke wsatria630@gmail.com (dev mode)', '::1', '2026-03-27 14:09:35'),
(68, NULL, 'LOGIN', 'Login berhasil via OTP dari ::1', '::1', '2026-03-27 14:10:01'),
(69, NULL, 'LOGOUT', 'Logout', '::1', '2026-03-27 14:10:11'),
(70, NULL, 'OTP_SENT', 'OTP dikirim ke wiramuhammad16@gmail.com (dev mode)', '::1', '2026-03-27 14:10:39'),
(71, 6, 'LOGIN', 'Login berhasil via OTP dari ::1', '::1', '2026-03-27 14:11:48'),
(72, 6, 'LOGOUT', 'Logout', '::1', '2026-03-27 14:23:19'),
(73, NULL, 'OTP_SENT', 'OTP dikirim ke wiramuhammad16@gmail.com', '::1', '2026-03-27 14:23:34'),
(74, 6, 'LOGIN', 'Login berhasil via OTP dari ::1', '::1', '2026-03-27 14:23:57'),
(75, 6, 'LOGOUT', 'Logout', '::1', '2026-03-27 14:24:06'),
(76, NULL, 'OTP_SENT', 'OTP dikirim ke wiramuhammad16@gmail.com', '::1', '2026-03-27 14:36:37'),
(77, 6, 'LOGIN', 'Login berhasil via OTP dari ::1', '::1', '2026-03-27 14:37:05'),
(78, 6, 'LOGOUT', 'Logout', '::1', '2026-03-27 14:38:55'),
(79, NULL, 'OTP_SENT', 'OTP dikirim ke wiramuhammad16@gmail.com', '::1', '2026-03-27 14:39:07'),
(80, 6, 'LOGIN', 'Login berhasil via OTP dari ::1', '::1', '2026-03-27 14:39:15'),
(81, 6, 'LOGOUT', 'Logout', '::1', '2026-03-27 14:40:43'),
(82, NULL, 'OTP_SENT', 'OTP dikirim ke wsatria630@gmail.com', '::1', '2026-03-27 14:41:34'),
(83, NULL, 'LOGIN', 'Login berhasil via OTP dari ::1', '::1', '2026-03-27 14:41:48'),
(84, NULL, 'LOGOUT', 'Logout', '::1', '2026-03-27 14:41:58'),
(85, NULL, 'OTP_SENT', 'OTP dikirim ke wiramuhammad16@gmail.com', '::1', '2026-03-27 14:42:05'),
(86, 6, 'LOGIN', 'Login berhasil via OTP dari ::1', '::1', '2026-03-27 14:42:20'),
(87, 6, 'LOGOUT', 'Logout', '::1', '2026-03-27 14:53:15'),
(88, NULL, 'OTP_SENT', 'OTP dikirim ke wiramuhammad16@gmail.com', '::1', '2026-03-27 14:57:32'),
(89, 6, 'LOGIN', 'Login berhasil via OTP dari ::1', '::1', '2026-03-27 14:57:45'),
(90, 6, 'LOGOUT', 'Logout', '::1', '2026-03-27 14:59:13'),
(91, NULL, 'OTP_SENT', 'OTP dikirim ke wsatria630@gmail.com', '::1', '2026-03-27 14:59:27'),
(92, NULL, 'LOGIN', 'Login berhasil via OTP dari ::1', '::1', '2026-03-27 14:59:47'),
(93, NULL, 'LOGOUT', 'Logout', '::1', '2026-03-27 14:59:51'),
(94, NULL, 'OTP_SENT', 'OTP dikirim ke wiramuhammad16@gmail.com', '::1', '2026-03-27 15:02:15'),
(95, 6, 'LOGIN', 'Login berhasil via OTP dari ::1', '::1', '2026-03-27 15:02:22'),
(96, 6, 'LOGOUT', 'Logout', '::1', '2026-03-27 15:05:13'),
(97, NULL, 'OTP_SENT', 'OTP dikirim ke wiramuhammad16@gmail.com', '::1', '2026-04-16 08:59:31'),
(98, 6, 'LOGIN', 'Login berhasil via OTP dari ::1', '::1', '2026-04-16 09:00:03'),
(99, 6, 'LOGOUT', 'Logout', '::1', '2026-04-16 12:05:41'),
(100, NULL, 'OTP_SENT', 'OTP dikirim ke wiramuhammad16@gmail.com', '::1', '2026-04-16 12:05:59'),
(101, 6, 'LOGIN', 'Login berhasil via OTP dari ::1', '::1', '2026-04-16 12:06:08'),
(102, 6, 'LOGOUT', 'Logout', '::1', '2026-04-16 12:53:14'),
(103, NULL, 'OTP_SENT', 'OTP dikirim ke wsatria630@gmail.com', '::1', '2026-04-16 12:53:25'),
(104, NULL, 'LOGIN', 'Login berhasil via OTP dari ::1', '::1', '2026-04-16 12:53:57'),
(105, NULL, 'ABSEN_MASUK', 'Masuk 2026-04-16 19:56:42, lokasi:Rumah, jarak:25m, acc:40m', '::1', '2026-04-16 12:56:42'),
(106, NULL, 'ABSEN_KELUAR', 'Keluar 2026-04-16 19:59:30, lokasi:Rumah, durasi:3m, acc:40m', '::1', '2026-04-16 12:59:30'),
(107, NULL, 'LOGOUT', 'Logout', '::1', '2026-04-16 12:59:49'),
(108, NULL, 'OTP_SENT', 'OTP dikirim ke wiramuhammad16@gmail.com', '::1', '2026-04-16 12:59:55'),
(109, 6, 'LOGIN', 'Login berhasil via OTP dari ::1', '::1', '2026-04-16 13:00:06'),
(110, 6, 'LOGOUT', 'Logout', '::1', '2026-04-16 13:02:12'),
(111, NULL, 'OTP_SENT', 'OTP dikirim ke wsatria630@gmail.com', '::1', '2026-04-16 13:02:26'),
(112, NULL, 'LOGIN', 'Login berhasil via OTP dari ::1', '::1', '2026-04-16 13:02:39'),
(113, NULL, 'ABSEN_MASUK', 'Masuk 2026-04-16 20:04:35, lokasi:Rumah, jarak:30m, terlambat:0s, acc:40m', '::1', '2026-04-16 13:04:35'),
(114, NULL, 'ABSEN_KELUAR', 'Keluar 2026-04-16 20:04:58, lokasi:Rumah, durasi:0m, pulang_cepat:0s, acc:40m', '::1', '2026-04-16 13:04:58'),
(115, NULL, 'LOGOUT', 'Logout', '::1', '2026-04-16 13:06:11'),
(116, NULL, 'OTP_SENT', 'OTP dikirim ke wiramuhammad16@gmail.com', '::1', '2026-04-16 13:06:18'),
(117, 6, 'LOGIN', 'Login berhasil via OTP dari ::1', '::1', '2026-04-16 13:06:31'),
(118, 6, 'LOGOUT', 'Logout', '::1', '2026-04-16 13:11:08'),
(119, NULL, 'OTP_SENT', 'OTP dikirim ke wsatria630@gmail.com', '::1', '2026-04-16 13:11:21'),
(120, NULL, 'LOGIN', 'Login berhasil via OTP dari ::1', '::1', '2026-04-16 13:11:31'),
(121, NULL, 'ABSEN_MASUK', 'Masuk 2026-04-16 20:12:00, lokasi:Rumah, jarak:0m, terlambat:43920s, acc:40m', '::1', '2026-04-16 13:12:00'),
(122, NULL, 'ABSEN_KELUAR', 'Keluar 2026-04-16 20:14:32, lokasi:Rumah, durasi:3m, pulang_cepat:0s, acc:40m', '::1', '2026-04-16 13:14:32'),
(123, NULL, 'ABSEN_MASUK', 'Masuk 2026-04-16 20:29:02, lokasi:Rumah, jarak:30m, terlambat:44942s, acc:40m', '::1', '2026-04-16 13:29:02'),
(124, NULL, 'ABSEN_KELUAR', 'Keluar 2026-04-16 20:29:39, lokasi:Rumah, durasi:1m, pulang_cepat:0s, acc:40m', '::1', '2026-04-16 13:29:39'),
(125, NULL, 'LOGOUT', 'Logout', '::1', '2026-04-16 13:30:06'),
(126, NULL, 'OTP_SENT', 'OTP dikirim ke wiramuhammad16@gmail.com', '::1', '2026-04-16 13:30:14'),
(127, 6, 'LOGIN', 'Login berhasil via OTP dari ::1', '::1', '2026-04-16 13:30:43'),
(128, 6, 'LOGOUT', 'Logout', '::1', '2026-04-16 13:38:33'),
(129, NULL, 'OTP_SENT', 'OTP dikirim ke wiramuhammad16@gmail.com', '::1', '2026-04-16 13:39:45'),
(130, 6, 'LOGIN', 'Login berhasil via OTP dari ::1', '::1', '2026-04-16 13:39:58'),
(131, 6, 'LOGOUT', 'Logout', '::1', '2026-04-16 13:51:54'),
(132, NULL, 'OTP_SENT', 'OTP dikirim ke wiramuhammad16@gmail.com', '::1', '2026-04-17 01:36:40'),
(133, 6, 'LOGIN', 'Login berhasil via OTP dari ::1', '::1', '2026-04-17 01:37:49'),
(134, 6, 'LOGOUT', 'Logout', '::1', '2026-04-17 01:47:36'),
(135, NULL, 'OTP_SENT', 'OTP dikirim ke wiramuhammad16@gmail.com', '::1', '2026-04-17 03:21:58'),
(136, 6, 'LOGIN', 'Login berhasil via OTP dari ::1', '::1', '2026-04-17 03:22:05'),
(137, 6, 'LOGOUT', 'Logout', '::1', '2026-04-17 03:22:27'),
(138, NULL, 'OTP_SENT', 'OTP dikirim ke wsatria630@gmail.com', '::1', '2026-04-17 03:22:39'),
(139, NULL, 'LOGIN', 'Login berhasil via OTP dari ::1', '::1', '2026-04-17 03:23:09'),
(140, NULL, 'LOGOUT', 'Logout', '::1', '2026-04-17 03:28:53'),
(141, NULL, 'OTP_SENT', 'OTP dikirim ke wiramuhammad16@gmail.com', '::1', '2026-04-17 03:29:00'),
(142, 6, 'LOGIN', 'Login berhasil via OTP dari ::1', '::1', '2026-04-17 03:29:10'),
(143, 6, 'LOGOUT', 'Logout', '::1', '2026-04-17 03:29:31'),
(144, NULL, 'OTP_SENT', 'OTP dikirim ke wsatria630@gmail.com', '::1', '2026-04-17 03:29:43'),
(145, NULL, 'LOGIN', 'Login berhasil via OTP dari ::1', '::1', '2026-04-17 03:29:53'),
(146, NULL, 'UPDATE_PROFIL', 'Memperbarui data profil', '::1', '2026-04-17 03:35:23'),
(147, NULL, 'UPDATE_PROFIL', 'Memperbarui data profil', '::1', '2026-04-17 03:36:05'),
(148, NULL, 'UPDATE_PROFIL', 'Memperbarui data profil', '::1', '2026-04-17 03:42:26'),
(149, NULL, 'UPDATE_PROFIL', 'Memperbarui data profil', '::1', '2026-04-17 03:43:40'),
(150, NULL, 'UPDATE_PROFIL', 'Memperbarui data profil', '::1', '2026-04-17 03:48:27'),
(151, NULL, 'UPDATE_PROFIL', 'Memperbarui data profil', '::1', '2026-04-17 03:48:31'),
(152, NULL, 'LOGOUT', 'Logout', '::1', '2026-04-17 03:48:45'),
(153, NULL, 'OTP_SENT', 'OTP dikirim ke wiramuhammad16@gmail.com', '::1', '2026-04-17 03:50:15'),
(154, 6, 'LOGIN', 'Login berhasil via OTP dari ::1', '::1', '2026-04-17 03:50:23'),
(155, 6, 'LOGOUT', 'Logout', '::1', '2026-04-17 03:50:50'),
(156, NULL, 'OTP_SENT', 'OTP dikirim ke wsatria630@gmail.com', '::1', '2026-04-17 03:51:01'),
(157, 19, 'LOGIN', 'Login berhasil via OTP dari ::1', '::1', '2026-04-17 03:51:14'),
(158, 19, 'LOGOUT', 'Logout', '::1', '2026-04-17 03:51:18'),
(159, NULL, 'OTP_SENT', 'OTP dikirim ke wsatria630@gmail.com', '::1', '2026-04-17 03:51:28'),
(160, 19, 'LOGIN', 'Login berhasil via OTP dari ::1', '::1', '2026-04-17 03:51:36'),
(161, 19, 'UPDATE_PROFIL', 'Memperbarui data profil', '::1', '2026-04-17 04:19:35'),
(162, 19, 'LOGOUT', 'Logout', '::1', '2026-04-17 04:19:58'),
(163, NULL, 'OTP_SENT', 'OTP dikirim ke wiramuhammad16@gmail.com', '::1', '2026-04-17 04:20:12'),
(164, 6, 'LOGIN', 'Login berhasil via OTP dari ::1', '::1', '2026-04-17 04:20:20'),
(165, 6, 'LOGOUT', 'Logout', '::1', '2026-04-17 04:21:31'),
(166, NULL, 'OTP_SENT', 'OTP dikirim ke wsatria630@gmail.com', '::1', '2026-04-17 04:21:37'),
(167, 19, 'LOGIN', 'Login berhasil via OTP dari ::1', '::1', '2026-04-17 04:21:50');

-- --------------------------------------------------------

--
-- Table structure for table `lokasi`
--

CREATE TABLE `lokasi` (
  `id` int(11) NOT NULL,
  `perusahaan_id` int(11) NOT NULL,
  `nama` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `alamat` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `latitude` decimal(10,8) NOT NULL,
  `longitude` decimal(11,8) NOT NULL,
  `radius_meter` int(11) DEFAULT 100 COMMENT 'Radius toleransi dalam meter',
  `status` enum('aktif','nonaktif') COLLATE utf8mb4_unicode_ci DEFAULT 'aktif',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `lokasi`
--

INSERT INTO `lokasi` (`id`, `perusahaan_id`, `nama`, `alamat`, `latitude`, `longitude`, `radius_meter`, `status`, `created_at`, `updated_at`) VALUES
(5, 1, 'RS Permata Hati', 'Sungai Pinang', '-1.48846827', '102.10298922', 100, 'aktif', '2026-03-25 02:47:55', '2026-03-25 02:47:55'),
(16, 1, 'Rumah', 'BCG 2 Untuk karyawan WFH', '-1.52180794', '102.12555680', 100, 'aktif', '2026-04-16 12:07:26', '2026-04-16 12:07:26');

-- --------------------------------------------------------

--
-- Table structure for table `otp_login`
--

CREATE TABLE `otp_login` (
  `id` int(11) NOT NULL,
  `email` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `otp` char(6) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) DEFAULT 0,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `otp_login`
--

INSERT INTO `otp_login` (`id`, `email`, `otp`, `expires_at`, `used`, `ip_address`, `created_at`) VALUES
(22, 'wiramuhammad16@gmail.com', '215284', '2026-04-17 10:26:52', 1, '::1', '2026-04-17 03:21:52'),
(23, 'wsatria630@gmail.com', '051753', '2026-04-17 10:27:34', 1, '::1', '2026-04-17 03:22:34'),
(24, 'wiramuhammad16@gmail.com', '936446', '2026-04-17 10:33:55', 1, '::1', '2026-04-17 03:28:55'),
(25, 'wsatria630@gmail.com', '832744', '2026-04-17 10:34:38', 1, '::1', '2026-04-17 03:29:38'),
(26, 'wiramuhammad16@gmail.com', '088564', '2026-04-17 10:55:10', 1, '::1', '2026-04-17 03:50:10'),
(27, 'wsatria630@gmail.com', '481483', '2026-04-17 10:55:56', 1, '::1', '2026-04-17 03:50:56'),
(28, 'wsatria630@gmail.com', '585326', '2026-04-17 10:56:20', 1, '::1', '2026-04-17 03:51:20'),
(29, 'wiramuhammad16@gmail.com', '570708', '2026-04-17 11:25:05', 1, '::1', '2026-04-17 04:20:05'),
(30, 'wsatria630@gmail.com', '825619', '2026-04-17 11:26:32', 1, '::1', '2026-04-17 04:21:32');

-- --------------------------------------------------------

--
-- Table structure for table `perusahaan`
--

CREATE TABLE `perusahaan` (
  `id` int(11) NOT NULL,
  `nama` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `alamat` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telepon` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `logo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `website` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `npwp` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `perusahaan`
--

INSERT INTO `perusahaan` (`id`, `nama`, `alamat`, `telepon`, `email`, `logo`, `website`, `npwp`, `created_at`, `updated_at`) VALUES
(1, 'PT. DailyFix', 'Gang Beo Nias, Blok A02, Muara Bungo - Jambi', '082177846209', 'info@dailyfix.id', NULL, 'www.dailyfix.id', '123123123', '2026-03-25 02:28:42', '2026-03-25 16:04:10'),
(2, 'PT. DailyFix Indonesia', 'Jl. Sudirman No. 123, Jakarta Pusat, DKI Jakarta 10220', '021-55555555', 'info@dailyfix.id', NULL, 'www.dailyfix.id', NULL, '2026-03-26 12:24:13', '2026-03-26 12:24:13'),
(3, 'PT. DailyFix Indonesia', 'Jl. Sudirman No. 123, Jakarta Pusat, DKI Jakarta 10220', '021-55555555', 'info@dailyfix.id', NULL, 'www.dailyfix.id', NULL, '2026-03-26 12:26:05', '2026-03-26 12:26:05'),
(4, 'PT. DailyFix Indonesia', 'Jl. Sudirman No. 123, Jakarta Pusat, DKI Jakarta 10220', '021-55555555', 'info@dailyfix.id', NULL, 'www.dailyfix.id', NULL, '2026-03-26 12:37:36', '2026-03-26 12:37:36');

-- --------------------------------------------------------

--
-- Table structure for table `shift`
--

CREATE TABLE `shift` (
  `id` int(11) NOT NULL,
  `perusahaan_id` int(11) NOT NULL,
  `nama` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `jam_masuk` time NOT NULL,
  `jam_keluar` time NOT NULL,
  `toleransi_terlambat_detik` int(11) DEFAULT 0 COMMENT 'Total detik toleransi keterlambatan',
  `toleransi_pulang_cepat_detik` int(11) NOT NULL DEFAULT 0,
  `keterangan` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('aktif','nonaktif') COLLATE utf8mb4_unicode_ci DEFAULT 'aktif',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `shift`
--

INSERT INTO `shift` (`id`, `perusahaan_id`, `nama`, `jam_masuk`, `jam_keluar`, `toleransi_terlambat_detik`, `toleransi_pulang_cepat_detik`, `keterangan`, `status`, `created_at`, `updated_at`) VALUES
(23, 1, 'Kantor1', '08:00:00', '16:00:00', 900, 300, '', 'aktif', '2026-03-27 14:37:51', '2026-04-16 13:33:04'),
(24, 1, 'Kantor2', '08:00:00', '13:00:00', 900, 300, '', 'aktif', '2026-03-27 14:38:06', '2026-04-16 13:33:11'),
(25, 1, 'Shift1', '08:00:00', '14:00:00', 900, 300, '', 'aktif', '2026-03-27 14:38:19', '2026-04-16 13:33:18'),
(26, 1, 'Shift2', '14:00:00', '20:00:00', 900, 300, '', 'aktif', '2026-03-27 14:38:36', '2026-04-16 13:33:25'),
(27, 1, 'Shift3', '20:00:00', '08:00:00', 900, 300, '', 'aktif', '2026-03-27 14:38:49', '2026-04-16 13:33:30');

-- --------------------------------------------------------

--
-- Table structure for table `smtp_settings`
--

CREATE TABLE `smtp_settings` (
  `id` int(11) NOT NULL,
  `perusahaan_id` int(11) NOT NULL,
  `host` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT 'smtp.gmail.com',
  `port` int(11) DEFAULT 587,
  `encryption` enum('tls','ssl','none') COLLATE utf8mb4_unicode_ci DEFAULT 'tls',
  `username` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `from_email` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `from_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `smtp_settings`
--

INSERT INTO `smtp_settings` (`id`, `perusahaan_id`, `host`, `port`, `encryption`, `username`, `password`, `from_email`, `from_name`, `is_active`, `updated_at`) VALUES
(1, 1, 'smtp.gmail.com', 587, 'tls', 'xxx', 'xxx', 'xxx', 'DailyFix Absensi', 1, '2026-04-17 04:24:13');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `absensi`
--
ALTER TABLE `absensi`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_absensi` (`karyawan_id`,`tanggal`),
  ADD KEY `jadwal_id` (`jadwal_id`),
  ADD KEY `shift_id` (`shift_id`);

--
-- Indexes for table `departemen`
--
ALTER TABLE `departemen`
  ADD PRIMARY KEY (`id`),
  ADD KEY `perusahaan_id` (`perusahaan_id`);

--
-- Indexes for table `izin`
--
ALTER TABLE `izin`
  ADD PRIMARY KEY (`id`),
  ADD KEY `karyawan_id` (`karyawan_id`),
  ADD KEY `disetujui_oleh` (`disetujui_oleh`);

--
-- Indexes for table `jabatan`
--
ALTER TABLE `jabatan`
  ADD PRIMARY KEY (`id`),
  ADD KEY `perusahaan_id` (`perusahaan_id`);

--
-- Indexes for table `jadwal`
--
ALTER TABLE `jadwal`
  ADD PRIMARY KEY (`id`),
  ADD KEY `perusahaan_id` (`perusahaan_id`),
  ADD KEY `shift_id` (`shift_id`),
  ADD KEY `lokasi_id` (`lokasi_id_unused`);

--
-- Indexes for table `jadwal_karyawan`
--
ALTER TABLE `jadwal_karyawan`
  ADD PRIMARY KEY (`id`),
  ADD KEY `karyawan_id` (`karyawan_id`),
  ADD KEY `jadwal_id` (`jadwal_id`);

--
-- Indexes for table `karyawan`
--
ALTER TABLE `karyawan`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nik` (`nik`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `perusahaan_id` (`perusahaan_id`),
  ADD KEY `jabatan_id` (`jabatan_id`),
  ADD KEY `departemen_id` (`departemen_id`),
  ADD KEY `lokasi_id` (`lokasi_id`);

--
-- Indexes for table `karyawan_lokasi`
--
ALTER TABLE `karyawan_lokasi`
  ADD PRIMARY KEY (`karyawan_id`,`lokasi_id`),
  ADD KEY `fk_kl_lokasi` (`lokasi_id`);

--
-- Indexes for table `log_aktivitas`
--
ALTER TABLE `log_aktivitas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `karyawan_id` (`karyawan_id`);

--
-- Indexes for table `lokasi`
--
ALTER TABLE `lokasi`
  ADD PRIMARY KEY (`id`),
  ADD KEY `perusahaan_id` (`perusahaan_id`);

--
-- Indexes for table `otp_login`
--
ALTER TABLE `otp_login`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_expires` (`expires_at`);

--
-- Indexes for table `perusahaan`
--
ALTER TABLE `perusahaan`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `shift`
--
ALTER TABLE `shift`
  ADD PRIMARY KEY (`id`),
  ADD KEY `perusahaan_id` (`perusahaan_id`);

--
-- Indexes for table `smtp_settings`
--
ALTER TABLE `smtp_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_perusahaan` (`perusahaan_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `absensi`
--
ALTER TABLE `absensi`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `departemen`
--
ALTER TABLE `departemen`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `izin`
--
ALTER TABLE `izin`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `jabatan`
--
ALTER TABLE `jabatan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `jadwal`
--
ALTER TABLE `jadwal`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `jadwal_karyawan`
--
ALTER TABLE `jadwal_karyawan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `karyawan`
--
ALTER TABLE `karyawan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `log_aktivitas`
--
ALTER TABLE `log_aktivitas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=168;

--
-- AUTO_INCREMENT for table `lokasi`
--
ALTER TABLE `lokasi`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `otp_login`
--
ALTER TABLE `otp_login`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `perusahaan`
--
ALTER TABLE `perusahaan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `shift`
--
ALTER TABLE `shift`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `smtp_settings`
--
ALTER TABLE `smtp_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `absensi`
--
ALTER TABLE `absensi`
  ADD CONSTRAINT `absensi_ibfk_1` FOREIGN KEY (`karyawan_id`) REFERENCES `karyawan` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `absensi_ibfk_2` FOREIGN KEY (`jadwal_id`) REFERENCES `jadwal` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `absensi_ibfk_3` FOREIGN KEY (`shift_id`) REFERENCES `shift` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `departemen`
--
ALTER TABLE `departemen`
  ADD CONSTRAINT `departemen_ibfk_1` FOREIGN KEY (`perusahaan_id`) REFERENCES `perusahaan` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `izin`
--
ALTER TABLE `izin`
  ADD CONSTRAINT `izin_ibfk_1` FOREIGN KEY (`karyawan_id`) REFERENCES `karyawan` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `izin_ibfk_2` FOREIGN KEY (`disetujui_oleh`) REFERENCES `karyawan` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `jabatan`
--
ALTER TABLE `jabatan`
  ADD CONSTRAINT `jabatan_ibfk_1` FOREIGN KEY (`perusahaan_id`) REFERENCES `perusahaan` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `jadwal`
--
ALTER TABLE `jadwal`
  ADD CONSTRAINT `jadwal_ibfk_1` FOREIGN KEY (`perusahaan_id`) REFERENCES `perusahaan` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `jadwal_ibfk_2` FOREIGN KEY (`shift_id`) REFERENCES `shift` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `jadwal_ibfk_3` FOREIGN KEY (`lokasi_id_unused`) REFERENCES `lokasi` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `jadwal_karyawan`
--
ALTER TABLE `jadwal_karyawan`
  ADD CONSTRAINT `jadwal_karyawan_ibfk_1` FOREIGN KEY (`karyawan_id`) REFERENCES `karyawan` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `jadwal_karyawan_ibfk_2` FOREIGN KEY (`jadwal_id`) REFERENCES `jadwal` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `karyawan`
--
ALTER TABLE `karyawan`
  ADD CONSTRAINT `karyawan_ibfk_1` FOREIGN KEY (`perusahaan_id`) REFERENCES `perusahaan` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `karyawan_ibfk_2` FOREIGN KEY (`jabatan_id`) REFERENCES `jabatan` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `karyawan_ibfk_3` FOREIGN KEY (`departemen_id`) REFERENCES `departemen` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `karyawan_ibfk_4` FOREIGN KEY (`lokasi_id`) REFERENCES `lokasi` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `karyawan_lokasi`
--
ALTER TABLE `karyawan_lokasi`
  ADD CONSTRAINT `fk_kl_karyawan` FOREIGN KEY (`karyawan_id`) REFERENCES `karyawan` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_kl_lokasi` FOREIGN KEY (`lokasi_id`) REFERENCES `lokasi` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `log_aktivitas`
--
ALTER TABLE `log_aktivitas`
  ADD CONSTRAINT `log_aktivitas_ibfk_1` FOREIGN KEY (`karyawan_id`) REFERENCES `karyawan` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `lokasi`
--
ALTER TABLE `lokasi`
  ADD CONSTRAINT `lokasi_ibfk_1` FOREIGN KEY (`perusahaan_id`) REFERENCES `perusahaan` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `shift`
--
ALTER TABLE `shift`
  ADD CONSTRAINT `shift_ibfk_1` FOREIGN KEY (`perusahaan_id`) REFERENCES `perusahaan` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `smtp_settings`
--
ALTER TABLE `smtp_settings`
  ADD CONSTRAINT `smtp_settings_ibfk_1` FOREIGN KEY (`perusahaan_id`) REFERENCES `perusahaan` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
