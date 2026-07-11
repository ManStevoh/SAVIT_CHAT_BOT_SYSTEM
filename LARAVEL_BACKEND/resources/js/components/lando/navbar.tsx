"use client"

import { useState } from "react"
import Link from "next/link"
import { Button } from "@/components/ui/button"
import { Menu, X } from "lucide-react"
import { AppLogoAndName } from "@/components/branding/AppLogoAndName"
import { cn } from "@/lib/utils"
import type { CmsLink } from "./types"

interface LandoNavbarProps {
  links?: CmsLink[]
  loginLabel?: string
  loginHref?: string
  signupLabel?: string
  signupHref?: string
  activePath?: string
}

export function LandoNavbar({
  links = [],
  loginLabel = "Log in",
  loginHref = "/login",
  signupLabel = "Sign up",
  signupHref = "/register",
  activePath = "/",
}: LandoNavbarProps) {
  const [open, setOpen] = useState(false)

  return (
